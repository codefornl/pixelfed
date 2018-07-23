<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth, Cache, Hashids;
use App\{Like, Profile, Status, User};
use App\Jobs\LikePipeline\LikePipeline;

class LikeController extends Controller
{
    public function __construct()
    {
      $this->middleware('auth');
    }

    public function store(Request $request)
    {
      $this->validate($request, [
        'item'    => 'required|integer',
      ]);

      $profile = Auth::user()->profile;
      $status = Status::withCount('likes')->findOrFail($request->input('item'));

      $count = $status->likes_count;

      if($status->likes()->whereProfileId($profile->id)->count() !== 0) {
        $like = Like::whereProfileId($profile->id)->whereStatusId($status->id)->firstOrFail();
        $like->forceDelete();
        $count--;
      } else {
        $like = new Like;
        $like->profile_id = $profile->id;
        $like->status_id = $status->id;
        $like->save();
        $count++;
        LikePipeline::dispatch($like);
      }

      $likes = Like::whereProfileId($profile->id)
               ->orderBy('id', 'desc')
               ->take(1000)
               ->pluck('status_id');
               
      Cache::put('api:like-ids:user:'.$profile->id,  $likes, 1440);

      if($request->ajax()) {
        $response = ['code' => 200, 'msg' => 'Like saved', 'count' => $count];
      } else {
        $response = redirect($status->url());
      }

      return $response;
    }
}
