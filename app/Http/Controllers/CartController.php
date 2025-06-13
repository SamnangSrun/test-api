<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Book;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Add book to cart
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $book = Book::find($request->book_id);

        if (!$book || $book->status !== 'approved') {
            return response()->json(['message' => 'Book not available for sale'], 400);
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $cartItem = $cart->items()->where('book_id', $book->id)->first();

        $existingQty = $cartItem ? $cartItem->quantity : 0;
        $totalQty = $existingQty + $request->quantity;

        if ($totalQty > $book->stock) {
            return response()->json([
                'message' => "Not enough stock. Only {$book->stock} left.",
            ], 400);
        }

        $data = [
            'quantity' => $totalQty,
            'price' => $book->price,
        ];

        if ($cartItem) {
            $cartItem->update($data);
        } else {
            $cart->items()->create([
                'book_id' => $book->id,
                'quantity' => $request->quantity,
                'price' => $book->price,
            ]);
        }

        return response()->json(['message' => 'Item added to cart'], 200);
    }

    /**
     * View all items in cart
     */
    public function viewCart()
    {
        $user = auth()->user();

        if (!$user || !$user->cart) {
            return response()->json(['message' => 'No cart found'], 404);
        }

        $cartItems = $user->cart->items()->with('book')->get();

        return response()->json($cartItems, 200);
    }

    /**
     * Update item quantity in cart
     */
    public function updateCartItem(Request $request, $itemId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();

        $cartItem = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $user->id))
            ->where('id', $itemId)
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Item not found in your cart'], 404);
        }

        $book = $cartItem->book;
        if ($request->quantity > $book->stock) {
            return response()->json(['message' => "Only {$book->stock} in stock."], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
            'price' => $book->price,
        ]);

        return response()->json(['message' => 'Cart item updated successfully']);
    }

    /**
     * Remove item from cart
     */
    public function removeCartItem($itemId)
    {
        $user = auth()->user();

        $cartItem = CartItem::whereHas('cart', fn ($q) => $q->where('user_id', $user->id))
            ->where('id', $itemId)
            ->first();

        if (!$cartItem) {
            return response()->json(['message' => 'Item not found in your cart'], 404);
        }

        $cartItem->delete();

        return response()->json(['message' => 'Cart item removed successfully']);
    }
}
