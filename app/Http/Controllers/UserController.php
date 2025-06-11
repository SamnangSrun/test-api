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
                'secure' => true
            ]
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

        $profileImageUrl = null;
        $profileImageId = null;

        if ($request->hasFile('profile_image')) {
            $uploaded = $this->uploadToCloudinary($request->file('profile_image'));
            $profileImageUrl = $uploaded['url'];
            $profileImageId = $uploaded['public_id'];
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'profile_image_url' => $profileImageUrl,
            'profile_image_id' => $profileImageId,
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
            // Delete old image from Cloudinary if exists
            if ($user->profile_image_id) {
                $this->deleteFromCloudinary($user->profile_image_id);
            }

            // Upload new image
            $uploaded = $this->uploadToCloudinary($request->file('profile_image'));
            $user->profile_image_url = $uploaded['url'];
            $user->profile_image_id = $uploaded['public_id'];
        }

        $user->fill($request->except('profile_image'));
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    // Delete User
    public function deleteUser(User $user)
    {
        if ($user->profile_image_id) {
            $this->deleteFromCloudinary($user->profile_image_id);
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

        if ($user->profile_image_id) {
            $this->deleteFromCloudinary($user->profile_image_id);
            $user->profile_image_url = null;
            $user->profile_image_id = null;
            $user->save();
        }

        return response()->json(['message' => 'Profile image deleted successfully', 'user' => $user]);
    }

    // Helper: Upload image to Cloudinary
    private function uploadToCloudinary($file)
    {
        $uploaded = $this->cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => 'profile_images',
            'transformation' => [
                ['width' => 500, 'height' => 500, 'crop' => 'limit']
            ],
        ]);

        return [
            'url' => $uploaded['secure_url'],
            'public_id' => $uploaded['public_id'],
        ];
    }

    // Helper: Delete image from Cloudinary
    private function deleteFromCloudinary($publicId)
    {
        try {
            $this->cloudinary->uploadApi()->destroy($publicId);
        } catch (\Exception $e) {
            // Optionally log error here
        }
    }
}
