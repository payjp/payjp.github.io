<?php

/*
決済を行うには `Charge` API を使用します。

下の例では、購入者の名前や配送先欄が用意されているフォーム内に PAY.JP Checkout を設置しています。
カード情報だけを PAY.JP 上に預け、アプリケーション側では PAY.JP Checkout から発行された
Token を使用して決済を行います。

※ `data-key` の部分は本番で使う際には管理画面内に個別で発行しているものに置き換えてください。
https://github.com/payjp/user-docs/tree/master/tutorial
*/

require_once('vendor/autoload.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $token = $_POST['payjp-token'];
  $amount = $_POST['amount'];

  \Payjp\Payjp::setApiKey('sk_test_c62fade9d045b54cd76d7036');

  try {
    $charge = \Payjp\Charge::create([
      'card' => $token,
      'amount'=> $amount,
      'currency' => 'jpy'
    ]);

    echo "決済が完了しました。<br>Charge ID = {$charge->id}\n";
    error_log(var_export($charge, true));

  } catch (\Payjp\Error\InvalidRequest $e) {
    echo "トークンは既に使用済みです。\n";
  }

  exit;
}

/*
2度目以降の決済時など、カード情報の入力を再度行いたくない場合は
最初の決済時に `Customer` オブジェクトと `Card` オブジェクトを作成し、
Customer ID をアプリケーション内の顧客情報と紐付けておきます。

$customer = \Payjp\Customer::retrieve('Customer ID');
$charge = \Payjp\Charge::create([
  'card' => $cus->default_card,
  'amount'=> $amount,
  'currency' => 'jpy'
]);
*/

?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta content="width=device-width,initial-scale=1.0" name="viewport">
  <link rel="stylesheet" href="//oss.maxcdn.com/semantic-ui/2.0.7/semantic.min.css" type="text/css" media="screen" charset="utf-8">
  <style media="screen">
    body {
      margin: 20px;
    }
    .ui.button {
      margin-top: 20px;
    }
  </style>
  <title>PAY.JP 実装サンプル</title>
</head>
<body>

  <form action="" method="post" class="ui form" >
    <h4 class="ui dividing header">配送先住所</h4>
    <div class="field">
      <label>名前</label>
      <div class="two fields">
        <div class="field">
          <input type="text" name="lastname" placeholder="磯野" value="磯野">
        </div>
        <div class="field">
          <input type="text" name="firstname" placeholder="カツオ" value="カツオ">
        </div>
      </div>
    </div>
    <div class="field">
      <label>住所</label>
      <div class="fields">
        <input type="text" placeholder="東京都渋谷区道玄坂1-2-3" value="東京都渋谷区道玄坂1-2-3" />
      </div>
    </div>

    <script
      type="text/javascript"
      src="https://checkout.pay.jp/"
      class="payjp-button"
      data-key="pk_test_0383a1b8f91e8a6e3ea0e2a9"
      data-partial="true">
    </script>

    <!--
      ここでは仮で hidden で金額を指定していますが、
      セッションなどに保存されているカートの合計金額を `amount` に渡します。
    -->
    <input type="hidden" name="amount" value="200">

    <button class="ui button">200円でリンゴを購入する</button>
  </form>

</body>
</html>
