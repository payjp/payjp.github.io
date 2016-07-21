<?php

/*
 * OAuth API を使った PAY ID 決済のサンプルコードです。
 * accounts, cards, addressesのscopeを取得し、登録済みカードによる決済と配送先住所やメールアドレスの自動入力を行っています。
 *
 * 具体的な画面遷移フローは下記になります。
 *  1. 決済情報、配送先入力フォームへアクセス
 *  2. 「PAY ID で決済する」を押下
 *  3. PAY IDログイン画面 （利用者のブラウザがすでにPAY IDにログインしていた場合は省略されます）
 *  4. PAY IDアクセス許可画面
 *  5. リダイレクトURLに遷移し、APIから情報を取得後入力フォームへ戻ってくる
 *
 */

require_once('vendor/autoload.php');

const AUTHORIZATON_ENDPOINT = 'https://id.pay.jp/.oauth2/authorize';
const TOKEN_ENDPOINT        = 'https://api.pay.jp/u/.oauth2/token';
const API_ENDPOINT_BASE     = 'https://api.pay.jp/u/v1';

/*
 * 下記の定数については、管理画面より取得できるものに置き換えてご利用ください。
 * APIKEY_SECRETにはAPIKEYの秘密キーを、
 * https://pay.jp/dashboard/settings/apikey
 * CLIENT_ID, CLIENT_SECRETにはOAuthClientの情報を設定してください。
 * https://pay.jp/dashboard/settings/oauth
 */
const APIKEY_SECRET = 'sk_test_c62fade9d045b54cd76d7036';
const CLIENT_ID = '7a025319dca2624cea68713243820c46f4200dfb';
const CLIENT_SECRET = '0e0f1464ed4761f500ecec242759021cd695de1e2ae4be56938e1eb';

session_start();

if (isset($_GET['code'])) {
    /*
    * アクセス許可画面からcodeをもってリダイレクトしてきた時の処理
    */

    /*
     * CSRFを防ぐためstateの検証を行うようにしてください
     */
    if($_SESSION['state'] != $_GET['state']) {
        echo "不正なリクエストです";
        exit;
    }

    /*
     * Token Requestを行い、アクセストークンを取得します
     */
    $token_request_body = array(
        'grant_type'    => 'authorization_code',
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET ,
        'code'          => $_GET['code'],
    );

    $req = curl_init(TOKEN_ENDPOINT);
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true );
    curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($token_request_body));
    $resp = json_decode(curl_exec($req), true);
    curl_close($req);

    $access_token = $resp['access_token'];

    /*
     * 各種APIをリクエストして情報を取得します
     * 各APIの詳細については https://pay.jp/docs/payjp-oauth-api の「API一覧」をご参照ください。
     */
    $req = curl_init(API_ENDPOINT_BASE . '/accounts');
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , 'Authorization: Bearer ' . $access_token ));
    $accounts = json_decode(curl_exec($req), true);

    $req = curl_init(API_ENDPOINT_BASE . '/cards/default');
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , 'Authorization: Bearer ' . $access_token ));
    $default_card = json_decode(curl_exec($req), true);

    $req = curl_init(API_ENDPOINT_BASE . '/cards/default/tokenize');
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_POST, true );
    curl_setopt($req, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , 'Authorization: Bearer ' . $access_token ));
    $token = json_decode(curl_exec($req), true);

    $req = curl_init(API_ENDPOINT_BASE . '/addresses');
    curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($req, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , 'Authorization: Bearer ' . $access_token ));
    $addresses = json_decode(curl_exec($req), true);
} elseif (isset($_GET['error'])) {
    /*
     * ユーザがアクセス許可をしなかった場合や、その他のエラーが発生した場合は、リダイレクト時にerrorが渡されます。
     */
    echo $_GET['error'];
    echo $_GET['error_description'];
    exit;
} else {
    /*
    *  下記 $aurhorize_url へのアクセスがAuthorization Requestとなります。
    *  URLパラメータの詳細については https://pay.jp/docs/payjp-oauth-api の「OAuth API利用の流れ」の章をご参照ください。
    */
    $_SESSION['state'] = md5(uniqid());
    $authorization_request_params = array(
        'response_type' => 'code',
        'client_id'     => CLIENT_ID,
        'scope'         => 'accounts cards addresses',
        'state'         => $_SESSION['state'],
    );
    $aurhorize_url = AUTHORIZATON_ENDPOINT . '?' . http_build_query($authorization_request_params);
}

/*
 * フォームがPOSTされたらサーバーサイドで決済処理を行う
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $token = $_POST['payjp-token'];
  $amount = $_POST['amount'];

  \Payjp\Payjp::setApiKey(APIKEY_SECRET);

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

  <form action="./" method="post" class="ui form" >
    <h4 class="ui dividing header">支払い方法</h4>
    <div class="field">
<?php
    if (isset($default_card) && isset($token)){
echo <<<EOF
<bold>利用カード情報</bold><br />
{$default_card['brand']}<br />
カード番号下4桁 {$default_card['last4']} <br />
有効期限: {$default_card['exp_month']} / {$default_card['exp_year']}<br />
名義: {$default_card['name']}
<input type="hidden" name="payjp-token" value="{$token['id']}">
EOF;
      } else {
echo <<<EOF
    <a href="{$aurhorize_url}">PAY IDで決済</a>
EOF;
      }
      ?>
    </div>
    <h4 class="ui dividing header">配送先住所</h4>
    <div class="field">
      <label>名前</label>
      <div class="two fields">
        <div class="field">
        <input type="text" name="lastname" placeholder="姓" value="<?php echo $addresses['last_name'] ?>">
        </div>
        <div class="field">
        <input type="text" name="firstname" placeholder="名" value="<?php echo $addresses['first_name'] ?>">
        </div>
      </div>
    </div>
    <div class="field">
      <label>住所</label>
      <div>
      <input type="text" name="address_zip" placeholder="〒" value="<?php echo $addresses['address_zip'] ?>" />
      </div>
      <div>
      <input type="text" name="address_state" placeholder="都道府県" value="<?php echo $addresses['address_state'] ?>" />
      </div>
      <div>
      <input type="text" name="address_city" placeholder="市町村" value="<?php echo $addresses['address_city'] ?>" />
      </div>
      <div>
      <input type="text" name="address_line" placeholder="番地・マンション名" value="<?php echo $addresses['address_line1'] . $addresses['address_line2'] ?>" />
      </div>
    </div>
    <div class="field">
      <label>メールアドレス</label>
      <div>
      <input type="text" name="email" placeholder="" value="<?php echo $accounts['email'] ?>" />
      </div>
    </div>
    <div class="field">
      <label>電話番号</label>
      <div>
      <input type="text" name="phone" placeholder="" value="<?php echo $addresses['address_phone'] ?>" />
      </div>
    </div>

    <!--
      ここでは仮で hidden で金額を指定していますが、
      セッションなどに保存されているカートの合計金額を `amount` に渡します。
    -->
    <input type="hidden" name="amount" value="200">

    <button class="ui button">200円でバナナを購入する</button>
  </form>

</body>
</html>
