<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request) {
        $user_type = ucwords(strtolower($request->user_type));
        if ($user_type ==="Externo"){
            $validateData = $request->validate([
                'name' => 'required|regex:/^[\pL\s\-]+$/u',
                'surname' => 'required|regex:/^[\pL\s\-]+$/u',
                'email'=>'email|required|unique:users',
                'password'=>'required|alpha_num|confirmed|min:8',
                'user_type' => 'required'
            ]);
        }else if($user_type==="Profesor" || $user_type==="Alumno" || $user_type==="Admin"){
            $validateData = $request->validate([
                'name' => 'required|regex:/^[\pL\s\-]+$/u',
                'surname' => 'required|regex:/^[\pL\s\-]+$/u',
                'email' => 'required|email|regex:/^([a-z\d\._-]+)@(elpoli.edu.co)$/|unique:users',
                'password'=>'required|alpha_num|confirmed|min:8',
                'user_type' => 'required'
            ]);
        }else {
            $data = array(
                'status' => 'error',
                'code' => 400,
                'message' => "El tipo de usuario {$user_type} no existe en nuestro sistema"
            );
            return response()->json($data, $data['code']);
        }


        $validateData['password'] = bcrypt($request->password);

        $user = User::create($validateData);
        event(new Registered($user));
        $accessToken = $user->createToken('authToken')->accessToken;

        return response(['user'=>$user,'access_token'=>$accessToken]);
    }

    public function login(Request $request) {
        $user = auth()->user();
        $userRole = $user->user_type;
        $accessToken = $user->createToken($user->email.'-'.now(),[$userRole]);
        return response()->json([
            'token' => $accessToken->accessToken,
            'name' => $user->name,
            'role' => $user->user_type
        ]);
    }

    public function active(Request $request) {
        $updateData = $request->validate([
            'email' => 'email|required',
            'is_active' => 'required|boolean'
        ]);

        $email = $request->email;
        $status = $request->is_active;
        $user = User::find(1)->where('email','=',$request->email)->first();

        if(!$user) {
            return response(['message'=>"No existe usuario con correo {$email}"]);
        }
        if($status === 1) {
            $message = "Usuario {$email} Activado";
        }else {
            $message = "Usuario {$email} Desactivado";
        }

        $user->is_active = $status;
        $user->save();
        return response(['message'=>$message]);
    }

    public function logout(Request $request) {
        $request->user()->token()->revoke();
        return response(['message' => 'Successfully logged out']);
    }

    public function users() {
        return User::all();
    }

    public function resendVerify(Request $request) {
        $email = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        $user = User::where('email',$email->getData())->first();

        if(!$user) {
            return response()
                ->json(['status' => '404', 'message' => 'Usuario no encontrado']);
        }

        if ($user->hasVerifiedEmail()) {
            return response(['status'=>'200','message'=>'Correo ya verificado']);
        }

        $user->sendEmailVerificationNotification();
        if ($request->wantsJson()) {
            return response(['status'=>'200','message' => 'Correo de Verificación Enviado']);
        }
    }
}
