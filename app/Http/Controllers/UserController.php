<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $name = explode(' ', $user->name, 2);
        $profile = UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['user_id' => $user->id, 'first_name' => $name[0], 'last_name' => $name[1] ?? null, 'preferred_language' => 'en', 'timezone' => 'UTC']
        );
        $profile->first_name = $profile->first_name ?: $name[0];
        $profile->last_name = $profile->last_name ?: ($name[1] ?? null);
        $profile->save();
        //TODO: need to add more details

        return view('profile.index', compact('user', 'profile'));
    }

    public function editProfile()
    {
        $user = auth()->user();
        $name = explode(' ', $user->name, 2);
        $profile = UserProfile::where('user_id', $user->id)->first();
        $profile->first_name = $name[0];
        $profile->last_name = $name[1] ?? null;
        //TODO: need to add more details

        return view('profile.edit', compact('user', 'profile'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'preferred_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'preferred_contact_method' => 'nullable|in:email,sms,whatsapp',
            'notification_preferences' => 'nullable|array',
            'avatar' => 'nullable|image|max:2048',
            'bio' => 'nullable|string|max:2000',
        ]);

        try {
            $profile = UserProfile::where('user_id', $request->user()->id)->first();

            if ($request->hasFile('avatar')) {
                $profile->avatar = $request->file('avatar')->store('avatars', 'public');
            }
            //TODO: need to fix this, update name didn't work properly.
            $profile->fill([
                'first_name' => $request->input('first_name', $profile->first_name),
                'last_name' => $request->input('last_name', $profile->last_name),
                'preferred_language' => $request->input('preferred_language', $profile->preferred_language),
                'timezone' => $request->input('timezone', $profile->timezone),
                'preferred_contact_method' => $request->input('preferred_contact_method', $profile->preferred_contact_method),
                'bio' => $request->input('bio', $profile->bio),
            ]);

            $profile->notification_preferences = $request->input('notification_preferences', $profile->notification_preferences);
            $profile->save();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred while updating your profile. Please try again.']);     
        }

        return redirect()->route('profile.index')->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|confirmed|min:6',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Password updated.');
    }
}