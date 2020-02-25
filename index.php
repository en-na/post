<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
	// ログインしている
	$_SESSION['time'] = time();
	$members = $db->prepare('SELECT * FROM members WHERE id=?');
	$members->execute(array($_SESSION['id']));
	$member = $members->fetch();
} else {
	// ログインしていない
	header('Location: login.php'); exit();
}

//投稿を記録する
if(!empty($_POST)){
    if($_POST['message'] != ''){
        $message = $db->prepare('INSERT INTO posts SET member_id=?,message=?,reply_post_id=?,created=NOW()');
        $message->execute(array(
         $member['id'],
         $_POST['message'],
         $_POST['reply_post_id']
        ));

        header('Location: index.php'); exit();
    }
}

// 以下いいねの重複チェック&インサートorデリート  join/index.php内の重複アカウントのチェックの応用(教科書p258)

if (isset($_REQUEST['likes'])) {
    $likes_check = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE member_id=? AND message_id=?');
    $likes_check->execute(array(
      $member['id'],
      $_REQUEST['likes']
    ));
    $likes_duplicate = $likes_check->fetch();

      if ($likes_duplicate['cnt'] > 0) {
        $delete_likes = $db->prepare('DELETE FROM likes WHERE member_id=? AND message_id=?');
        $delete_likes->execute(array(
          $member['id'],
          $_REQUEST['likes']
        ));
     } else {
        $insert_likes = $db->prepare('INSERT INTO likes SET member_id=?, message_id=?, created=NOW()');
        $insert_likes->execute(array(
        $member['id'],
        $_REQUEST['likes']
      ));
     }
  }

// 以上いいねの重複チェック&インサートorデリート

// 以下リツイートの重複チェック&インサートorデリート  join/index.php内の重複アカウントのチェックの応用(教科書p258)
if (isset($_REQUEST['retweet'])) {
    $retweet_check = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE member_id=? AND retweet_id=?');
    $retweet_check->execute(array(
      $member['id'],
      $_REQUEST['retweet']
    ));
    $retweet_duplicate = $retweet_check->fetch();

       if ($retweet_duplicate['cnt'] > 0) {
         $delete_retweet = $db->prepare('DELETE FROM posts WHERE member_id=? AND retweet_id=?');
         $delete_retweet->execute(array(
          $member['id'],
          $_REQUEST['retweet']
         ));
         header('Location: index.php');
         exit();
        } else {
       //リツイート文取得は、117行目//返信の場合の応用(教科書p272)
        $retweet_text = $db->prepare('SELECT m.name, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
        $retweet_text->execute(array($_REQUEST['retweet']));
        $retweet_table = $retweet_text->fetch();
        $retweet_message = $member['name'] . 'さんがリツイートしました' . '  ' . $retweet_table['message'];

        $insert_retweet = $db->prepare('INSERT INTO posts SET message=?, member_id=?,retweet_id=?, created=NOW()');
        $insert_retweet->execute(array(
         $retweet_message,
         $member['id'],
         $_REQUEST['retweet']
        ));
        header('Location: index.php');
        exit();
       }
  }

// 以上リツイートの重複チェック&インサートorデリート

//ページを取得する
$page = $_REQUEST['page'];
if($page==''){
    $page=1;
}
$page = max($page,1);

//最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts ');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt']/5);
$page = min($page,$maxPage);

$start = ($page-1)*5;


//投稿を取得する &likesテーブルをリレーションして、いいね数を取得
$posts = $db->prepare('SELECT m.name, m.picture, p.*, COUNT(l.message_id) AS like_cnt
FROM members m, posts p LEFT JOIN likes l ON p.id=l.message_id WHERE m.id=p.member_id GROUP BY p.id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1,$start,PDO::PARAM_INT);
$posts->execute();


// 返信の場合
if (isset($_REQUEST['res'])) {
	$response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m,	posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
		$response->execute(array($_REQUEST['res']));
		$table = $response->fetch();
		$message = '@' . $table['name'] . ' ' . $table['message'];
    }

// 本文内のURLにリンクを設定
function makeLink($value) {
	return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)",'<a href="\1\2">\1\2</a>' , $value);
	}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>ひとこと掲示板</title>

	<link rel="stylesheet" href="css/style.css" />
</head>

<body>
<div id="wrap">
  <div id="head">
    <h1>ひとこと掲示板</h1>
  </div>
  <div id="content">
  <div style="text-align: right"><a href="logout.php">ログアウト</a></div>

	<form action="" method="post">
	<dl>
	 <dt><?php echo htmlspecialchars($member['name'], ENT_QUOTES); ?>さん、メッセージをどうぞ</dt>
	 <dd>
	 <textarea name="message" cols="50" rows="5"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></textarea>
     <input type="hidden" name="reply_post_id" value="<?php echo htmlspecialchars($_REQUEST['res'], ENT_QUOTES); ?>" />
	 </dd>
	</dl>
	<div>
	<input type="submit" value="投稿する" />
	</div>
	</form>

<?php foreach ($posts as $post): ?>

<div class="msg">
<img src="member_picture/<?php echo htmlspecialchars($post['picture'], ENT_QUOTES); ?>" width="48" height="48" alt="<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?>" />
<p><?php echo makeLink(htmlspecialchars($post['message'], ENT_QUOTES));?><span class="name">（<?php echo htmlspecialchars($post['name'], ENT_QUOTES); ?>）</span>
[<a href="index.php?res=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">Re</a>]</p>
<p class="day"><a href="view.php?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
<?php echo htmlspecialchars($post['created'], ENT_QUOTES); ?></a>

<?php if ($post['reply_post_id'] > 0): ?>
<a href="view.php?id=<?php echo htmlspecialchars($post['reply_post_id'], ENT_QUOTES); ?>">返信元のメッセージ</a>
<?php endif; ?>

<?php if($_SESSION['id'] == $post['member_id']):?>
[<a href="delete.php?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>" style="color:#F33;">削除</a>]
<?PHP endif; ?>

<!--以下いいねの表示 join/index.php内の重複アカウントのチェックの応用(教科書p258)-->
<?php
$likes_cnt = $db->prepare('SELECT COUNT(*) AS cnt FROM likes WHERE member_id=? AND message_id=?');
$likes_cnt->execute(array(
    $member['id'],
    $post['id']
     ));
$like_cnt = $likes_cnt->fetch();
?>

<?php if ($like_cnt['cnt'] > 0): ?>
<a href="index.php?likes=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">&#9829;</a>
  <?php else: ?>
  <a href="index.php?likes=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">&#9825;</a>
<?php endif; ?>
<?php echo htmlspecialchars($post['like_cnt'], ENT_QUOTES); ?>
<!--以上いいねの表示-->


<!--以下リツイートの表示 join/index.php内の重複アカウントのチェックの応用(教科書p258)-->
<?php
$retweets_cnt = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE member_id=? AND retweet_id=?');
$retweets_cnt->execute(array(
    $member['id'],
    $post['id']
     ));
$retweet_cnt = $retweets_cnt->fetch();
?>

<?php if ($retweet_cnt['cnt'] > 0): ?>
<a href="index.php?retweet=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">リツイート</a>
  <?php else: ?>
  <a href="index.php?retweet=<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">リツイート</a>
<?php endif; ?>
<!--以上リツイートの表示-->

 </p>
 </div>
<?php endforeach; ?>

<ul class="paging">
<?php if ($page > 1) { ?>
<li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
<?php } else { ?>
<li>前のページへ</li>
<?php } ?>
<?php if ($page < $maxPage) { ?>
<li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
<?php } else { ?>
<li>次のページへ</li>
<?php } ?>
</ul>

  </div>

</div>
</body>
</html>