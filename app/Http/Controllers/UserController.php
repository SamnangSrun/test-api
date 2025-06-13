<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Cloudinary\Cloudinary;

class UserController extends Controller
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

    // Sign Up
    public function signUp(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        $imageUrl = null;
        $publicId = null;

        if ($request->hasFile('profile_image')) {
            $uploadedFile = $request->file('profile_image')->getRealPath();

            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'folder' => 'pf_user',
                'upload_preset' => 'pf_user', // optional, use if you configured presets
            ]);

            $imageUrl = $uploaded['secure_url'];
            $publicId = $uploaded['public_id'];
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'profile_image' => $imageUrl,
            'profile_public_id' => $publicId,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User created successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    // Update Profile
    public function updateProfile(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        if ($request->hasFile('profile_image')) {
            // Optionally, delete old image from Cloudinary if needed
            if ($user->profile_public_id) {
                $this->deleteFromCloudinary($user->profile_public_id);
            }

            $uploadedFile = $request->file('profile_image')->getRealPath();

            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'folder' => 'pf_user',
                'upload_preset' => 'pf_user', // optional
            ]);

            $user->profile_image = $uploaded['secure_url'];
            $user->profile_public_id = $uploaded['public_id'];
        }

        // Update other fields except profile_image (already handled)
        $user->name = $request->input('name', $user->name);
        $user->email = $request->input('email', $user->email);

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    // Delete User
    public function deleteUser(User $user)
    {
        if ($user->profile_public_id) {
            $this->deleteFromCloudinary($user->profile_public_id);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // List Users (admin only)
    public function listUsers(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::latest()->get();

        return response()->json(['users' => $users]);
    }

    // Delete only profile image
    public function deleteProfileImage(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->profile_public_id) {
            $this->deleteFromCloudinary($user->profile_public_id);
            $user->profile_image = null;
            $user->profile_public_id = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile image deleted successfully',
            'user' => $user,
        ]);
    }

    // Helper: Delete image from Cloudinary
    private function deleteFromCloudinary($publicId)
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId);
        } catch (\Exception $e) {
            // Log or handle error if needed
        }
    }
}
