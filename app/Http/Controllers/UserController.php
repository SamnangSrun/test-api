<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UserController extends Controller
{
    // Sign In
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
            'user' => $user,
        ]);
    }

   public function signUp(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'profile_image_url' => 'nullable|string', // Cloudinary URL
            'profile_public_id' => 'nullable|string' // Cloudinary public ID
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'profile_image' => $request->profile_image_url,
            'profile_public_id' => $request->profile_public_id,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    // Updated Update Profile method
    public function updateProfile(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'profile_image_url' => 'nullable|string',
            'profile_public_id' => 'nullable|string',
            'remove_profile_image' => 'sometimes|boolean'
        ]);

        // Handle profile image removal
        if ($request->input('remove_profile_image')) {
            if ($user->profile_public_id) {
                Cloudinary::destroy($user->profile_public_id);
            }
            $user->profile_image = null;
            $user->profile_public_id = null;
        }

        // Update profile image if new URL provided
        if ($request->has('profile_image_url') && $request->profile_image_url) {
            // Delete old image if exists
            if ($user->profile_public_id) {
                Cloudinary::destroy($user->profile_public_id);
            }
            
            $user->profile_image = $request->profile_image_url;
            $user->profile_public_id = $request->profile_public_id;
        }

        // Update other fields
        $user->name = $request->input('name', $user->name);
        $user->email = $request->input('email', $user->email);

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    // Delete User
    public function deleteUser(User $user)
    {
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // List Users (Admin Only)
    public function listUsers(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::latest()->get();

        return response()->json(['users' => $users]);
    }

    // Delete only the user's profile image
    public function deleteProfileImage(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->profile_image) {
            $user->profile_image = null;
            $user->save();
        }

        return response()->json(['message' => 'Profile image deleted successfully', 'user' => $user]);
    }


    
    public function getSignature()
    {
        return response()->json([
            'cloud_name' => config('cloudinary.cloud_name'),
            'api_key' => config('cloudinary.api_key'),
            'upload_preset' => config('cloudinary.upload_preset'),
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $uploadedFile = Cloudinary::upload($request->file('file')->getRealPath(), [
            'folder' => 'user_profile_images',
            'transformation' => [
                'width' => 500,
                'height' => 500,
                'crop' => 'fill'
            ]
        ]);

        return response()->json([
            'secure_url' => $uploadedFile->getSecurePath(),
            'public_id' => $uploadedFile->getPublicId(),
        ]);
    }
}
