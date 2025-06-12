<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Cloudinary\Cloudinary;

class BookController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    public function listAllBooks(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admins can view all books.'], 403);
        }

        $books = Book::with(['user', 'category'])->get();
        return response()->json(['message' => 'List of all books', 'data' => $books]);
    }

    public function listBooks(Request $request)
    {
        $books = Book::with('category')->where('status', 'approved')->get();
        return response()->json(['books' => $books]);
    }

    public function viewBook($id)
    {
        $book = Book::with('category', 'user')
            ->where('id', $id)
            ->where('status', 'approved')
            ->first();

        if (!$book) {
            return response()->json(['message' => 'Book not found or not approved'], 404);
        }

        return response()->json(['message' => 'Book detail', 'book' => $book]);
    }

    public function addBook(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'seller') {
            return response()->json(['message' => 'Only sellers can add books.'], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'author' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer|min:0',
            'cover_image' => 'nullable|image',
            'category_name' => 'required|string',
        ]);

        $category = Category::firstOrCreate(['name' => $request->category_name]);

        $imageUrl = null;
        $publicId = null;

        if ($request->hasFile('cover_image')) {
            $uploadedFile = $request->file('cover_image')->getRealPath();

            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'folder' => 'ml_default',
                'upload_preset' => 'ml_default',
            ]);

            $imageUrl = $uploaded['secure_url'];
            $publicId = $uploaded['public_id'];
        }

        $book = Book::create([
            'name' => $request->name,
            'author' => $request->author,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'category_id' => $category->id,
            'seller_id' => $user->id,
            'status' => 'pending',
            'cover_image' => $imageUrl,
            'cover_public_id' => $publicId,
        ]);

        return response()->json(['message' => 'Book submitted for approval', 'book' => $book]);
    }

    public function approveBook(Request $request, Book $book)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $book->status = 'approved';
        $book->save();

        return response()->json(['message' => 'Book approved successfully']);
    }

    public function rejectBook(Request $request, Book $book)
    {
        $admin = $request->user();
        if ($admin && $admin->role === 'admin') {
            $validated = $request->validate([
                'reject_note' => 'required|string|max:500',
            ]);

            $book->status = 'disapproved';
            $book->reject_note = $validated['reject_note'];
            $book->save();

            return response()->json(['message' => 'Book rejected with note', 'book' => $book]);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function deleteBookById(Request $request, $id)
    {
        $user = $request->user();
        $book = Book::find($id);

        if (!$book) {
            return response()->json(['message' => 'Book not found.'], 404);
        }

        if ($user->role !== 'seller' || $book->seller_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($book->cover_public_id) {
            $this->deleteFromCloudinary($book->cover_public_id);
        }

        $book->delete();
        return response()->json(['message' => 'Book deleted successfully.']);
    }

    public function updateBook(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'seller') {
            return response()->json(['message' => 'Only sellers can update books.'], 403);
        }

        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'Book not found.'], 404);
        }

        if ($book->seller_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'author' => 'sometimes|required|string',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric',
            'stock' => 'sometimes|required|integer|min:0',
            'cover_image' => 'nullable|image',
            'category_name' => 'sometimes|required|string|exists:categories,name',
        ]);

        if (isset($validated['name'])) $book->name = $validated['name'];
        if (isset($validated['author'])) $book->author = $validated['author'];
        if (isset($validated['description'])) $book->description = $validated['description'];
        if (isset($validated['price'])) $book->price = $validated['price'];
        if (isset($validated['stock'])) $book->stock = $validated['stock'];

        if (isset($validated['category_name'])) {
            $category = Category::where('name', $validated['category_name'])->first();
            if ($category) {
                $book->category_id = $category->id;
            } else {
                return response()->json(['message' => 'Category not found.'], 404);
            }
        }

        if ($request->hasFile('cover_image')) {
            if ($book->cover_public_id) {
                $this->deleteFromCloudinary($book->cover_public_id);
            }

            $uploadedFile = $request->file('cover_image')->getRealPath();
            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'folder' => 'ml_default',
                'upload_preset' => 'ml_default',
            ]);

            $book->cover_image = $uploaded['secure_url'];
            $book->cover_public_id = $uploaded['public_id'];
        }

        $book->status = 'pending';
        $book->reject_note = null;

        $book->save();
        $book->load('category');
        return response()->json(['message' => 'Book updated and re-submitted.', 'book' => $book]);
    }

    private function deleteFromCloudinary($publicId)
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId);
        } catch (\Exception $e) {
            // Log or handle error if needed
        }
    }

    public function search(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $query = Book::with('category')->where('status', 'approved');
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $query->where('name', 'like', $request->name . '%');
        $books = $query->get();

        return response()->json(['books' => $books]);
    }

    public function myBooks(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'seller') {
            return response()->json(['message' => 'Only sellers can view this.'], 403);
        }

        $books = Book::with('category')->where('seller_id', $user->id)->get();
        return response()->json(['books' => $books]);
    }
}