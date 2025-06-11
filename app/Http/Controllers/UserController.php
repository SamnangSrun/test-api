<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UserController extends Controller
{
    private function formatUserResponse(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'profile_image_url' => $user->profile_image ?? null,
        ];
    }

    public function signIn(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    public function signUp(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        $imageUrl = null;

        if ($request->hasFile('profile_image')) {
            $imageUrl = Cloudinary::upload($request->file('profile_image')->getRealPath())->getSecurePath();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'profile_image' => $imageUrl,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->formatUserResponse($user),
        ], 201);
    }

    public function updateProfile(Request $request, User $user)
    {
        // Auth check: self or admin only
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->hasFile('profile_image')) {
            // Just upload new image and overwrite URL
            $imageUrl = Cloudinary::upload($request->file('profile_image')->getRealPath())->getSecurePath();
            $user->profile_image = $imageUrl;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    public function deleteUser(Request $request, User $user)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // No image delete from Cloudinary because no public_id saved

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function listUsers(Request $request)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::latest()->get();

        return response()->json([
            'users' => $users->map(fn ($u) => $this->formatUserResponse($u)),
        ]);
    }

    public function deleteProfileImage(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Just nullify image URL, no Cloudinary deletion
        $user->profile_image = null;
        $user->save();

        return response()->json([
            'message' => 'Profile image deleted successfully',
            'user' => $this->formatUserResponse($user),
        ]);
    }
}
