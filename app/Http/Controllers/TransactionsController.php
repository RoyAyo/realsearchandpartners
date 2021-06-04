<?php

namespace App\Http\Controllers;

use DB;
use App\User;
use App\Transaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use Tymon\JWTAuth\Facades\JWTAuth;

class TransactionsController extends Controller
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
    public function index()
    {
        try {
            $id = auth()->user()->id;

            // $transactions =  Transaction::where('user_id',$id)->orWhere('user_pay_id',$id)->get();

            $transactions = Transaction::where('user_id','=',$id)
            ->leftJoin('users','users.id','=','transactions.user_id')->select('transactions.*','users.id as user_id','users.full_name as senders_full_name')
            ->orWhere('user_pay_id','=',$id)
            ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions
                ]
            ]);

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
        if($request->amount < 100){
            return $this->returnError('You cannot transfer less than #100',400);
        }

        if($auth_user->balance < $request->amount){
            return $this->returnError('You do not have sufficient balance',400);
        }

        $user = User::find($request->id);

        if(!$user){
            return $this->returnError('Invalid User selected for transfer',400);
        }

        try {
            
            DB::beginTransaction();

            User::find($auth_user->id)->decrement('balance',$request->amount);

            $user->increment('balance',$request->amount);

            $user->save();

            Transaction::create([
                'user_id' => $auth_user->id,
                'user_pay_id' => $request->id,
                'amount' => $request->amount,
                'status' => 'Success' 
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'msg' => 'Transaction successsful'
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Transaction::create([
                'user_id' => $auth_user->id,
                'user_pay_id' => $request->id,
                'amount' => $request->amount,
                'status' => 'Failed' 
            ]);

            return response()->json([
                'success' => false,
                'msg' => 'Transaction Failed'
            ]);

        }

    }

    public function initializePayment(Request $request)
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

    public function verifyPayment(Request $request)
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
};