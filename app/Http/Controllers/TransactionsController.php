<?php

namespace App\Http\Controllers;

use App\User;
use App\Transaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    //
    public function userTransactions()
    {
        try {
            $id = auth()->user()->id;

            $transactions =  Transactions::where('user_id',$id)->orWhere('user_pay_id',$id)->join();

            return $this->response()->json();

        } catch (\Exception $e) {
            return $this->returnError('Server Error',500);
        }

    }


    public function transfer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'amount' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->first(),400);
        }

        $auth_user = Auth::user();

        // ensure suffiecient balance
        if($auth_user->balance < $request->amount){
            return $this->returnError('You do not have sufficient balance',400);
        } 

        try {
            
            DB::beginTransaction();

            User::find($auth_user->id)->decrement('balance',$request->amount);

            User::find($request->id)->increment('balance',$request->amount);

            Transaction::create([
                'user_id' => $auth_user->id,
                'user_pay_id' => $request->id,
                'amount' => $request->amount,
                'status' => 'Success' 
            ]);

            DB::commit();

            return $this->response([
                'success' => true,
                'msg' => 'Transaction successsful'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Transaction::create([
                'user_id' => $auth_user->id,
                'user_pay_id' => $request->id,
                'amount' => $request->amount,
                'staus' => 'failed' 
            ]);

            return $this->response([
                'success' => false,
                'msg' => 'Transaction Faild'
            ]);

        }

    }

    public function initializePayment()
    {

        $validator = Validator::make($request->all(), [
            'amount' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->returnError($validator->errors()->first(),400);
        }

        $user = Auth::user();

        $paystack = new Yabcon\Paystack(env('PAYSTACK_TEST_SECRET'));

        try{
            $tranx = $paystack->transaction->initialize([
                'amount'=>$request->amount * 100,    // in kobo
                'email'=>$user->email
            ]);

            User::find($user->id)->update(['reference'=>$tranx->data->reference]);

            return $this->response([
                'success' => true,
                'msg' => 'Payment initialized',
                'url' => $tranx->data->authorization_url
            ]);


        } catch(\Yabacon\Paystack\Exception\ApiException $e){
            return $this->returnError($e->getMessage(),500);
        }

    }

    public function verifyPayment()
    {
    
        $user = Auth::user();

        $paystack = new Yabacon\Paystack(SECRET_KEY);
        
        try
        {
            $tranx = $paystack->transaction->verify([
                'reference'=>$user->reference, // unique to transactions
            ]);
        } catch(\Yabacon\Paystack\Exception\ApiException $e){
            return $this->returnError($e->getMessage(),500);
        }

        if ('success' === $tranx->data->status) {

            User::find($user->id)->update(['reference'=>'', 'balance', $tranx->data->amount]);

            return response()->json([
                'success' => true,
                'msg' => 'Transaction completed'
            ]);
        }

        return $this->returnError('Unable to verify Payment',422);
    }
}