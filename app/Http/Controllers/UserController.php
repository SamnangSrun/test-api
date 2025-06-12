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

        if ($request->hasFile('profile_image')) {
            $uploadedFile = $request->file('profile_image')->getRealPath();

            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'upload_preset' => 'pf_user',
            ]);

            $imageUrl = $uploaded['secure_url']; // ប្រើ secure_url ជំនួសអោយ URL ពេញ
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
            // លុបរូបភាពចាស់ពី Cloudinary បើមាន
            if ($user->profile_image) {
                $publicId = basename($user->profile_image, '.' . pathinfo($user->profile_image, PATHINFO_EXTENSION));
                $this->cloudinary->uploadApi()->destroy($publicId);
            }

            $uploadedFile = $request->file('profile_image')->getRealPath();

            $uploaded = $this->cloudinary->uploadApi()->upload($uploadedFile, [
                'upload_preset' => 'pf_user',
            ]);

            $user->profile_image = $uploaded['secure_url'];
        }

        $user->update($request->except('profile_image'));

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }
}