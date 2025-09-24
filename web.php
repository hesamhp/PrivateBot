<?php
session_start();

// تنظیم محدودیت زمان ارسال (به ثانیه)
$limitTime = 30;

// گرفتن شناسه کاربر مقصد از URL
$from_id = $_GET['id'] ?? null;

if (!$from_id || !is_numeric($from_id)) {
    die("شناسه نامعتبر است.");
}

// محدودیت تایم: شمارنده و پیام خطا (در فرم نمایش داده شود)
$timeLeft = 0;
if (isset($_SESSION["last_sent_$from_id"])) {
    $diff = time() - $_SESSION["last_sent_$from_id"];
    if ($diff < $limitTime) {
        $timeLeft = $limitTime - $diff;
        $error = "⏳ لطفاً صبر کنید تا ارسال بعدی فعال شود. ($timeLeft ثانیه باقی مانده)";
    }
}

// خواندن نام کاربر از فایل data/name.json (اگر موجود باشد)، در غیر این صورت id نمایش داده شود
$namesFile = __DIR__ . "/data/name.json";
$names = file_exists($namesFile) ? json_decode(file_get_contents($namesFile), true) : [];
$displayName = $names[$from_id] ?? $from_id;
$defaultNameValue = $names[$from_id] ?? '';

// بررسی وجود پوشه کاربر قبل از نمایش فرم
$userDir = __DIR__ . "/data/users/$from_id";
if (!is_dir($userDir)) {
    echo '<body style="margin:0; padding:0; background:#5563DE; font-family:\'Kalameh\', sans-serif; display:flex; justify-content:center; align-items:center; min-height:100vh;">';
    echo '<div class="container" style="background:#fff; padding:30px 20px; border-radius:15px; box-shadow:0 15px 40px rgba(0,0,0,0.2); width:90%; max-width:450px; text-align:center;">';
    echo '<div class="error-box" style="display:flex; align-items:center; justify-content:center; color:#721c24; font-weight:bold; font-size:16px;">';
    echo '<i class="fas fa-times-circle" style="margin-right:8px;"></i>';
    echo '<span>❌ کاربر مورد نظر موجود نیست.</span>';
    echo '</div>';
    echo '</div>';
    echo '</body>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['captcha_a'] = rand(1, 9);
    $_SESSION['captcha_b'] = rand(1, 9);
}
$captchaA = $_SESSION['captcha_a'];
$captchaB = $_SESSION['captcha_b'];

// کنترل نمایش لودر فقط در اولین بار
$showLoader = !isset($_SESSION['page_loaded']);
$_SESSION['page_loaded'] = true;

// اگر فرم ارسال شد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');

    if ($captcha != ($captchaA + $captchaB)) {
        $error = "مقدار کپچا اشتباه است!";
    } elseif (!$message) {
        $error = "پیام نمی‌تواند خالی باشد!";
    } else {
        // ذخیره پیام در فایل
        $pvDir = __DIR__ . "/data/pv_targets";
        if (!file_exists($pvDir)) mkdir($pvDir, 0755, true);

        $finalMessage = $message;
        file_put_contents("$pvDir/pv_target_$from_id.txt", $finalMessage);

        // ارسال پیام اطلاع‌رسانی به کاربر مقصد
        $telegramToken = "7639538621:AAFmF1ac7iecNPgDSuWCtQDxCVRfq3nvTtI"; // توکن ربات
        $sendName = "ناشناس";
        $notifyText = "📩 پیام جدید از طریق سایت ثبت شد.\n";
        $notifyText .= "فرستنده: $sendName\n";
        $notifyText .= "پیام: $message";

        // تابع ارسال پیام به تلگرام
        function sendTelegramMessage($chat_id, $text, $token)
        {
            $url = "https://api.telegram.org/bot$token/sendMessage";
            $data = [
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                ],
            ];
            $context  = stream_context_create($options);
            file_get_contents($url, false, $context);
        }

        // ارسال پیام
        sendTelegramMessage($from_id, $notifyText, $telegramToken);

        $_SESSION["last_sent_$from_id"] = time();

        // بازنشانی اعداد کپچا پس از ارسال موفقیت‌آمیز
        unset($_SESSION['captcha_a'], $_SESSION['captcha_b']);

        // ذخیره پیام موفقیت در session
        $_SESSION['success_msg'] = "✅ پیام شما با موفقیت ثبت شد و به کاربر ارسال خواهد شد.";

        // ریدایرکت به همان صفحه برای پاک شدن POST و جلوگیری از دوباره نمایش
        header("Location: web.php?id=$from_id");
        exit;
    }
}

// خواندن پیام موفقیت از session و حذف آن بلافاصله بعد از خواندن
$success = $_SESSION['success_msg'] ?? '';
if (isset($_SESSION['success_msg'])) {
    unset($_SESSION['success_msg']);
}
?>

<!DOCTYPE html>
<html lang="فا">

<head>
    <meta charset="UTF-8">
    <title>ارسال پیام ناشناس به <?php echo htmlspecialchars($displayName); ?></title>
    <link rel="icon" href="https://hesvm.ir/pv/data/assets/logo.ico" type="image/x-icon">
    <style>
        @font-face {
            font-family: 'Kalameh';
            src: url('https://hesvm.ir/pv/data/assets/kalameh.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Kalameh', sans-serif;
            background: #5563DE; /* تغییر به بنفش */
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding-top: 50px;
            box-sizing: border-box;
        }

        .container {
            background: #ffffff;
            padding: 30px 20px;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 450px;
            text-align: center;
            direction: rtl;
            box-sizing: border-box;
        }

        textarea.message-box {
            width: 100%;
            text-align: right;
            direction: rtl;
            resize: vertical;
            /* فقط تغییر ارتفاع */
            min-width: 100%;
            box-sizing: border-box;
            padding: 12px 15px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s;
            min-height: 100px;
            max-height: 300px;
            font-family: 'Kalameh', sans-serif;
        }

        textarea.message-box:focus {
            border-color: #5563DE;
            box-shadow: 0 0 8px rgba(85, 99, 222, 0.3);
            outline: none;
        }

        button,
        .link-btn {
            width: 100%;
            max-width: 200px; /* عرض یکسان برای هر دو دکمه */
            box-sizing: border-box;
        }
        button {
            background: #5563DE;
            color: #fff;
            border: none;
            padding: 14px 25px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
            font-family: 'Kalameh', sans-serif;
        }

        button:hover {
            background: #74ABE2;
        }

        p {
            margin: 10px 0;
            font-size: 14px;
            text-align: center;
        }

        @media (min-width: 768px) {
            button {
                width: 50%;
            }
        }

        textarea.message-box::placeholder,
        input::placeholder {
            font-family: 'Kalameh', sans-serif;
            color: #aaa;
        }

        @media (max-width: 480px) {
            body {
                margin: 0;
                padding: 0;
                background: #5563DE; /* موبایل هم بنفش */
                display: block;
                min-height: 100vh;
                box-sizing: border-box;
            }

            .container {
                width: 100%; /* عرض تمام صفحه */
                max-width: 100%;
                height: auto; /* ارتفاع خودکار با محتوا */
                min-height: 100vh; /* حداقل ارتفاع کل صفحه */
                padding: 20px;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                overflow-y: auto; /* اسکرول در صورت محتوا طولانی */
            }

            h2 {
                font-size: 26px;
                margin-bottom: 5px;
                text-align: center;
            }

            p {
                font-size: 28px;
                margin-top: 0;
                text-align: center;
            }

            textarea.message-box {
                font-size: 18px;
                min-height: 160px;
                width: 100%;
            }

            button, .link-btn {
                font-size: 18px;
                max-width: 100%;
                width: 100%;
                padding: 16px 20px;
            }

            textarea.message-box::placeholder,
            input::placeholder {
                font-size: 16px;
            }

            #charCount {
                font-size: 14px;
                text-align: right;
            }
        }

        .spinner {
            margin-left: 8px;
        }

        .success-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 10px 0;
            font-weight: bold;
            gap: 8px;
        }

        .error-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 10px 15px;
            margin: 10px 0;
            font-weight: bold;
            gap: 8px;
        }
        .captcha-input {
            width: 100%;
            max-width: 150px;
            padding: 8px;
            margin: 5px auto 10px auto;
            display: block;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        @keyframes pulseShadow {
            0% {
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
            50% {
                box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            }
            100% {
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            }
        }

        .profile-img {
            width: 200px;
            height: auto;
            border-radius: 50%;
            display: block;
            margin: 0 auto;
        }

        .pulseShadow {
            animation: pulseShadow 2s infinite;
        }

        #loader {
            position: fixed;
            width: 100%;
            height: 100%;
            background: #5563DE;
            display: flex;
            justify-content: center;
            align-items: center;
            top: 0;
            left: 0;
            z-index: 9999;
        }

        .loader-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .loader-logo {
            width: 150px; /* افزایش اندازه لوگو از 100px به 150px */
            height: auto;
            margin-bottom: 5px; /* کاهش فاصله از اسم ربات از 15px به 5px */
        }

        .loader-title {
            color: #fff;
            font-family: 'Kalameh', sans-serif;
            margin-bottom: 8px;
        }

        .loader-slogan {
            color: #fff;
            font-family: 'Kalameh', sans-serif;
            font-size: 16px;
            margin-bottom: 15px;
            margin-top: 3px;
            text-align: center;
        }

        .spinner-loader {
            width: 60px;
            height: 60px;
            border: 8px solid #fff;
            border-top: 8px solid #74ABE2;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .link-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border: 2px solid #5563DE;
            border-radius: 8px;
            background: #fff;
            color: #5563DE;
            font-family: 'Kalameh', sans-serif;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        .link-btn:hover {
            background: #5563DE;
            color: #fff;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php if ($showLoader): ?>
    <div id="loader">
        <div class="loader-content">
            <img src="https://hesvm.ir/pv/data/assets/logo.png" alt="Logo" class="loader-logo">
            <h1 class="loader-title">StarHideBot</h1>
            <p class="loader-slogan">امنیت و ناشناسی در دستان شما</p>
            <div class="spinner-loader"></div>
        </div>
    </div>
    <?php endif; ?>

    <?php 
    $userProfilePath = __DIR__ . "/data/users/" . $from_id . "/profile.jpg";
    if (file_exists($userProfilePath)) {
        $imageUrl = "https://hesvm.ir/pv/data/users/" . $from_id . "/profile.jpg";
    } else {
        $imageUrl = "https://hesvm.ir/pv/data/assets/profile.jpg";
    }
    ?>
    <div class="container">
        <div style="text-align:center; margin-bottom:20px;">
            <!-- تصویر جدید بالای پروفایل -->
            <img src="https://hesvm.ir/pv/data/assets/header.jpg" alt="تصویر بالای پروفایل" style="width:250px; height:auto; margin-bottom:10px;" oncontextmenu="return false;" draggable="false">

            <!-- تصویر پروفایل کاربر -->
            <img id="profileImg" src="<?php echo $imageUrl; ?>" alt="تصویر" class="profile-img" style="opacity:0;" oncontextmenu="return false;" draggable="false">
        </div>
        <h2 style="margin-bottom:2px;">ارسال پیام ناشناس به</h2>
        <p style="color:#8000FF; font-size:28px; margin-top:0;"><?php echo htmlspecialchars($displayName); ?></p>

        <?php if (!empty($error)) : ?>
            <div class="error-box">
                <i class="fas fa-times-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)) : ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="post">
            <textarea name="message" placeholder="پیام خود را اینجا بنویسید..." rows="4" required class="message-box" maxlength="500"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            <div style="background:#eee; border-radius:5px; height:8px; width:100%; margin:5px 0;">
                <div id="progressBar" style="height:100%; width:0%; background:#5563DE; border-radius:5px;"></div>
            </div>
            <p id="charCount" style="text-align:right; font-size:12px; color:#555;">500 کاراکتر باقی مانده</p>
            <p>برای تایید، حاصل جمع <?php echo $captchaA; ?> + <?php echo $captchaB; ?> را وارد کنید:</p>
            <input type="number" name="captcha" required class="captcha-input">
            <div style="display:flex; justify-content:center;">
                <button type="submit" id="sendBtn" <?php echo ($timeLeft > 0) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                    <span class="btn-text">ارسال پیام</span>
                    <i class="fas fa-paper-plane"></i>
                    <span class="spinner" style="display:none;"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </form>
        <div style="display:flex; justify-content:center; margin-top:10px;">
            <a href="https://t.me/StarHideBot?start=weblink" class="link-btn">
                <i class="fas fa-link"></i>
                <span>ساخت لینک ناشناس من</span>
            </a>
        </div>
        <div style="text-align:center; margin-top:20px; font-size:14px; color:#333;">
            قدرت گرفته از 
            <a href="https://t.me/StarHideBot" target="_blank" style="color:#8000FF; text-decoration:none;">StarHide</a>
        </div>
    </div>

<script>
const profileImg = document.getElementById('profileImg');
profileImg.onload = () => {
    profileImg.style.transition = 'opacity 1s';
    profileImg.style.opacity = 1;
    profileImg.classList.add('pulseShadow');
};

const form = document.querySelector('form');
const button = form.querySelector('button');
const btnText = button.querySelector('.btn-text');
const spinner = button.querySelector('.spinner');
const textarea = document.querySelector('.message-box');
const charCount = document.getElementById('charCount');

textarea.addEventListener('input', () => {
    const remaining = 500 - textarea.value.length;
    charCount.textContent = remaining + ' کاراکتر باقی مانده';
    const percent = (textarea.value.length / 500) * 100;
    document.getElementById('progressBar').style.width = percent + '%';
});

form.addEventListener('submit', () => {
    button.disabled = true;
    btnText.textContent = 'در حال ارسال...';
    spinner.style.display = 'inline-block';
});

const loader = document.getElementById('loader');
if (loader) {
    window.addEventListener('load', () => {
        setTimeout(() => {
            loader.style.transition = 'opacity 0.5s';
            loader.style.opacity = 0;
            setTimeout(() => loader.style.display = 'none', 500);
        }, 2000);
    });
}

<?php if ($timeLeft > 0): ?>
let timeLeft = <?php echo $timeLeft; ?>;
const sendBtn = document.getElementById('sendBtn');
const errorBox = document.querySelector('.error-box span');
const countdown = setInterval(() => {
    if (timeLeft <= 0) {
        clearInterval(countdown);
        sendBtn.disabled = false;
        sendBtn.style.opacity = 1;
        sendBtn.style.cursor = 'pointer';
        if (errorBox) errorBox.textContent = '';
    } else {
        if (errorBox) errorBox.textContent = "⏳ لطفاً صبر کنید تا ارسال بعدی فعال شود. (" + timeLeft + " ثانیه باقی مانده)";
        timeLeft--;
    }
}, 1000);
<?php endif; ?>
</script>

</body>

</html>