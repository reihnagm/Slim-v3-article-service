<?php

namespace App\Controllers;

use App\Controllers\Controller;
use App\Models\Article;
use Ramsey\Uuid\Uuid;
use App\Models\ArticleImage;
use App\Helper\Helper;

class ArticleController extends Controller
{
  public function index($req, $res)
  {
    $http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");
    $page = (int) isset($_GET["page"]) ? $_GET["page"] : 1;
    $search = (string) isset($_GET["search"]) ? $_GET["search"] : "";
    $sort = (string) isset($_GET["sort"]) == "newer" ? "DESC" : "ASC";
    $sortBy = (string) isset($_GET["sortBy"]) ? $_GET["sortBy"] : "title";
    $show = (int) isset($_GET["show"]) ? $_GET["show"] : 5;
    $offset = ($page - 1) * $show;
    $articles = Article::select('uid', 'title', 'body', 'user_uid', 'event_uid', 'created_at', 'updated_at')
      ->with(['user:uid,name,email', 'event:uid,name', 'tags'])
      ->orderBy($sortBy, $sort)
      ->where('title', 'like', '%' . $search . '%')
      ->offset($offset)->limit($show)->get();
    $total = $articles->count();
    $perPage = ceil($total / $show);
    $prevPage = $page === 1 ? 1 : $page - 1;
    $nextPage = $page === $perPage ? 1 : $page + 1;
    $data = [];
    foreach ($articles as $article) {
      $image = isset(ArticleImage::select('imageurl')->where('article_uid', $article->uid)->orderBy('id', 'desc')->first()->imageurl) ? ArticleImage::select('imageurl')->where('article_uid', $article->uid)->orderBy('id', 'desc')->first()->imageurl : null;
      $nestedData["uid"] = $article->uid;
      $nestedData["title"] = $article->title;
      $nestedData["body"] = $article->body;
      $nestedData["image"] = $image ? $http . $_SERVER['HTTP_HOST'] . '/' . $image : $http . $_SERVER['HTTP_HOST'] . '/assets/images/default-img-article.png';
      $nestedData["user_uid"] = $article->user_uid;
      $nestedData["event_uid"] = $article->event_uid;
      $nestedData["created_at"] = $article->created_at;
      $nestedData["updated_at"] = $article->updated_at;
      $nestedData["user"] = [
        "uid" => $article->user->uid,
        "name" => $article->user->name,
        "email" => $article->user->email
      ];
      $nestedData["event"] = [
        "uid" => $article->event->uid,
        "name" => $article->event->name
      ];
      $nestedData["tags"] = $article->tags;
      $data[] = $nestedData;
    }
    // $_SERVER['SERVER_NAME'] => localhost
    // $_SERVER['HTTP_HOST'] => localhost:8080
    // $_SERVER['REQUEST_URI'] => localhost/api/
    return Helper::response($res, 200, false, "Ok.", [
      "articles" => $data,
      "perPage" => $perPage,
      "nextPage" => $nextPage,
      "prevPage" => $prevPage,
      "nextUrl" => $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?page=" . $nextPage,
      "prevUrl" => $http . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?page=" . $prevPage,
      "total" => $total
    ]);
  }
  public function show($req, $res, $args)
  {
    $slug = $args["slug"];
    $article = Article::select('uid', 'title', 'body', 'user_uid', 'event_uid', 'created_at', 'updated_at')
      ->with(['user:uid,name,email', 'event:uid,name', 'tags'])
      ->where("slug", "like", "%" . $slug . "%")
      ->firstOrFail();
    return Helper::response($res, 200, false, "Ok.", $article);
  }
  public function store($req, $res)
  {
    $uid = Uuid::uuid4();
    $title = $req->getParsedBody()["title"];
    $body = $req->getParsedBody()["body"];
    $tags = $req->getParsedBody()["tags"];
    $userUid = $req->getParsedBody()["user_uid"];
    $eventUid = $req->getParsedBody()["event_uid"];
    try {
      if (trim($title) == "") {
        throw new \Exception('Title is Required.');
      }
      if (trim($body) == "") {
        throw new \Exception('Body is Required.');
      }
      if (trim($userUid) == "") {
        throw new \Exception("User Uid is Required.");
      }
      if (trim($eventUid) == "") {
        throw new \Exception("Event Uid is Required.");
      }
      Article::create([
        "uid" => $uid,
        "title" => $title,
        "slug" => Helper::slugify($title),
        "body" => $body,
        "user_uid" => $userUid,
        "event_uid" => $eventUid
      ])->tags()->attach($tags);
      return Helper::response($res, 201, false, "Ok.", []);
    } catch (\Exception $e) {
      return Helper::response($res, 500, true, $e->getMessage(), []);
    }
  }
  public function update($req, $res, $args)
  {
    $uid = $args["uid"];
    $title = $req->getParsedBody()["title"];
    $body = $req->getParsedBody()["body"];
    $tags = $req->getParsedBody()["tags"];
    $eventUid = $req->getParsedBody()["event_uid"];
    $article = Article::where('uid', $uid)->firstOrFail();
    try {
      if (trim($title) == "") {
        throw new \Exception("Title Required.");
      }
      if (trim($body) == "") {
        throw new \Exception("Body Required.");
      }
      $article->title = $title;
      $article->slug = Helper::slugify($title);
      $article->body = $body;
      $article->event_uid = $eventUid;
      $article->tags()->sync($tags);
      $article->update();
      return Helper::response($res, 200, false, "Ok.", []);
    } catch (\Exception $e) {
      return Helper::response($res, 500, true, $e->getMessage(), []);
    }
  }
  public function destroy($req, $res, $args)
  {
    $uid = $args["uid"];
    $tags = $req->getParsedBody()["tags"];
    $article = Article::where("uid", $uid)->firstOrFail();
    $article->tags()->detach($tags);
    $article->delete();
    return Helper::response($res, 200, false, "Ok.", []);
  }
}
