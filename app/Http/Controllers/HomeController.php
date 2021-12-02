<?php

namespace App\Http\Controllers;

use App\Events\StartVideoChat;
use App\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $users = User::where('id', '!=', auth()->id())->get();
        return view('home', compact('users'));
    }

    public function call_to_user(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['signalData'] = $request->signal_data;
        $data['from'] = auth()->id();
        $data['callerName'] = auth()->user()->name;
        $data['type'] = 'incomingCall';

        broadcast(new StartVideoChat($data));
    }

    public function acceptCall(Request $request)
    {
        $data['userToCall'] = $request->to;
        $data['signal'] = $request->signal;
        $data['to'] = $request->to;
        $data['type'] = 'callAccepted';
        broadcast(new StartVideoChat($data))->toOthers();
    }

    public function declineCall(Request $request)
    {
        $data['userToCall'] = $request->to;
        $data['to'] = $request->to;
        $data['type'] = 'callDeclined';
        broadcast(new StartVideoChat($data))->toOthers();
    }
    public function onAnotherCall(Request $request)
    {
        $data['userToCall'] = $request->to;
        $data['to'] = $request->to;
        $data['type'] = 'onAnotherCall';
        broadcast(new StartVideoChat($data))->toOthers();
    }
    public function endCall(Request $request)
    {
        $data['userToCall'] = $request->to;
        $data['to'] = $request->to;
        $data['type'] = 'endCall';
        broadcast(new StartVideoChat($data))->toOthers();
    }
}
