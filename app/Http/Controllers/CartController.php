<?php
namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    /**
     * Add item to cart or update quantity
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1|max:100', // Added max limit
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Use database transaction for atomic operations
        return DB::transaction(function () use ($user, $validated) {
            $book = Book::where('id', $validated['book_id'])
                       ->where('status', 'approved')
                       ->lockForUpdate() // Prevent concurrent modifications
                       ->first();

            if (!$book) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book not available for purchase'
                ], 400);
            }

            // Get or create user's cart
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            
            // Check existing cart item
            $cartItem = $cart->items()
                            ->where('book_id', $book->id)
                            ->first();

            $requestedQuantity = $validated['quantity'];
            $existingQuantity = $cartItem ? $cartItem->quantity : 0;
            $totalQuantity = $existingQuantity + $requestedQuantity;

            // Validate stock availability
            if ($totalQuantity > $book->stock) {
                $available = $book->stock - $existingQuantity;
                $available = max(0, $available); // Ensure not negative
                
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock. You can add up to ' . $available . ' more.',
                    'max_available' => $available
                ], 400);
            }

            // Update or create cart item
            if ($cartItem) {
                $cartItem->update([
                    'quantity' => $totalQuantity,
                    'price' => $book->price
                ]);
            } else {
                $cart->items()->create([
                    'book_id' => $book->id,
                    'quantity' => $requestedQuantity,
                    'price' => $book->price
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart',
                'cart_item_count' => $cart->items()->count()
            ]);
        });
    }

    /**
     * Get current user's cart contents
     */
    public function viewCart()
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $cart = $user->cart()->with(['items.book.seller'])->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Your cart is empty',
                'items' => [],
                'total' => 0
            ]);
        }

        // Calculate totals
        $items = $cart->items->map(function ($item) {
            return [
                'id' => $item->id,
                'book_id' => $item->book_id,
                'title' => $item->book->name,
                'author' => $item->book->author,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->price * $item->quantity,
                'cover_image' => $item->book->cover_image
            ];
        });

        $total = $items->sum('subtotal');

        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => $total,
            'item_count' => $items->count()
        ]);
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        return DB::transaction(function () use ($validated, $itemId) {
            $user = auth()->user();
            
            $cartItem = CartItem::with(['book', 'cart'])
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->where('id', $itemId)
                ->lockForUpdate()
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found in your cart'
                ], 404);
            }

            if ($validated['quantity'] > $cartItem->book->stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock. Only ' . $cartItem->book->stock . ' available.',
                    'max_available' => $cartItem->book->stock
                ], 400);
            }

            $cartItem->update([
                'quantity' => $validated['quantity'],
                'price' => $cartItem->book->price // Update price in case it changed
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated',
                'subtotal' => $cartItem->price * $cartItem->quantity
            ]);
        });
    }

    /**
     * Remove item from cart
     */
    public function removeCartItem($itemId)
    {
        $user = auth()->user();
        
        $cartItem = CartItem::whereHas('cart', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('id', $itemId)->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found in your cart'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart',
            'remaining_items' => $user->cart->items()->count()
        ]);
    }
}