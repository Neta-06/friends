<?php
session_start();
require_once "config/database.php";
require_once "config/functions.php";

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'tr';
$translations = loadLanguage($lang);

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$show_verification = false;
$registered_phone = '';

if ($_POST) {
    // Kayıt formu gönderildi
    if (!isset($_POST['verification_code'])) {
        // İlk adım: Telefon ve şifre kontrolü
        $cep_telefonu = preg_replace('/[^0-9]/', '', $_POST['cep_telefonu']);
        $sifre = $_POST['sifre'];
        $sifre_tekrar = $_POST['sifre_tekrar'];
        $ad = trim($_POST['ad']);
        $soyad = trim($_POST['soyad']);
        $cinsiyet = $_POST['cinsiyet'];

        // Validasyonlar
        if (empty($cep_telefonu) || strlen($cep_telefonu) < 10) {
            $error = "Geçerli bir cep telefonu numarası giriniz.";
        } elseif (empty($ad) || empty($soyad)) {
            $error = "Ad ve soyad alanları zorunludur.";
        } elseif (strlen($sifre) < 6) {
            $error = "Şifre en az 6 karakter olmalıdır.";
        } elseif ($sifre !== $sifre_tekrar) {
            $error = "Şifreler eşleşmiyor.";
        } else {
            // Telefon numarası kontrolü
            $check_query = "SELECT id FROM kullanicilar WHERE cep_telefonu = :cep_telefonu";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":cep_telefonu", $cep_telefonu);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $error = "Bu telefon numarası zaten kayıtlı.";
            } else {
                // Doğrulama kodu oluştur
                $dogrulama_kodu = generateVerificationCode();
                $dogrulama_zamani = date('Y-m-d H:i:s');

                // Geçici kullanıcı kaydı
                $_SESSION['temp_user'] = [
                    'cep_telefonu' => $cep_telefonu,
                    'sifre' => password_hash($sifre, PASSWORD_DEFAULT),
                    'ad' => $ad,
                    'soyad' => $soyad,
                    'cinsiyet' => $cinsiyet,
                    'dogrulama_kodu' => $dogrulama_kodu,
                    'dogrulama_zamani' => $dogrulama_zamani
                ];

                // WhatsApp mesajı gönder (simülasyon)
                $message = "DostApp doğrulama kodunuz: " . $dogrulama_kodu;
                $whatsapp_sent = sendWhatsAppMessage($cep_telefonu, $message);

                if ($whatsapp_sent) {
                    $show_verification = true;
                    $registered_phone = $cep_telefonu;
                    $success = "Doğrulama kodunuz WhatsApp ile gönderildi.";
                } else {
                    $error = "WhatsApp mesajı gönderilemedi. Lütfen tekrar deneyin.";
                    unset($_SESSION['temp_user']);
                }
            }
        }
    } else {
        // İkinci adım: Doğrulama kodu kontrolü
        $verification_code = $_POST['verification_code'];
        
        if (isset($_SESSION['temp_user']) && $_SESSION['temp_user']['dogrulama_kodu'] === $verification_code) {
            // Doğrulama başarılı, kullanıcıyı kaydet
            $temp_user = $_SESSION['temp_user'];
            
            // Kodu doğrulama zamanını kontrol et (10 dakika)
            $code_time = strtotime($temp_user['dogrulama_zamani']);
            $current_time = time();
            
            if (($current_time - $code_time) > 600) { // 10 dakika
                $error = "Doğrulama kodu süresi dolmuş. Lütfen tekrar deneyin.";
                unset($_SESSION['temp_user']);
            } else {
                // Kullanıcıyı veritabanına kaydet
                $query = "INSERT INTO kullanicilar (cep_telefonu, sifre, ad, soyad, cinsiyet, dogrulama_kodu, aktif, kayit_tarihi) 
                         VALUES (:cep_telefonu, :sifre, :ad, :soyad, :cinsiyet, '', 1, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":cep_telefonu", $temp_user['cep_telefonu']);
                $stmt->bindParam(":sifre", $temp_user['sifre']);
                $stmt->bindParam(":ad", $temp_user['ad']);
                $stmt->bindParam(":soyad", $temp_user['soyad']);
                $stmt->bindParam(":cinsiyet", $temp_user['cinsiyet']);
                
                if ($stmt->execute()) {
                    $success = "Kayıt başarılı! Giriş yapabilirsiniz.";
                    unset($_SESSION['temp_user']);
                    
                    // 3 saniye sonra login sayfasına yönlendir
                    header("refresh:3;url=login.php?lang=" . $lang);
                } else {
                    $error = "Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        } else {
            $error = "Geçersiz doğrulama kodu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['register']; ?> - <?php echo $translations['site_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <h2><?php echo $translations['site_title']; ?></h2>
                            <p class="text-muted"><?php echo $translations['register']; ?></p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if (!$show_verification): ?>
                        <!-- Kayıt Formu -->
                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ad</label>
                                        <input type="text" class="form-control" name="ad" value="<?php echo isset($_POST['ad']) ? htmlspecialchars($_POST['ad']) : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Soyad</label>
                                        <input type="text" class="form-control" name="soyad" value="<?php echo isset($_POST['soyad']) ? htmlspecialchars($_POST['soyad']) : ''; ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cinsiyet</label>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="card gender-option text-center p-3 border" data-gender="erkek" onclick="selectGender('erkek')">
                                            <i class="fas fa-male fa-2x mb-2"></i>
                                            <div>Erkek</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card gender-option text-center p-3 border" data-gender="kadin" onclick="selectGender('kadin')">
                                            <i class="fas fa-female fa-2x mb-2"></i>
                                            <div>Kadın</div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="cinsiyet" id="selectedGender" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cep Telefonu</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" name="cep_telefonu" placeholder="5xx xxx xx xx" value="<?php echo isset($_POST['cep_telefonu']) ? htmlspecialchars($_POST['cep_telefonu']) : ''; ?>" required>
                                </div>
                                <small class="form-text text-muted">WhatsApp ile doğrulama kodu gönderilecektir.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" class="form-control" name="sifre" id="password" required minlength="6">
                                <div class="form-text">
                                    <small>Şifre en az 6 karakter olmalıdır.</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Şifre Tekrar</label>
                                <input type="password" class="form-control" name="sifre_tekrar" id="confirmPassword" required>
                                <div class="form-text">
                                    <small id="passwordMatch"></small>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Kullanım koşullarını</a> okudum ve kabul ediyorum.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-user-plus me-2"></i>Kayıt Ol
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- Doğrulama Formu -->
                        <form method="POST" id="verificationForm">
                            <div class="text-center mb-4">
                                <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                                <h4>WhatsApp Doğrulama</h4>
                                <p class="text-muted">
                                    <strong><?php echo $registered_phone; ?></strong> numarasına doğrulama kodu gönderildi.
                                </p>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Doğrulama Kodu</label>
                                <input type="text" class="form-control verification-input" name="verification_code" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                                <div class="form-text">
                                    6 haneli kodu giriniz
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Doğrula ve Kaydı Tamamla
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resendCode()">
                                    <i class="fas fa-redo me-2"></i>Kodu Tekrar Gönder
                                </button>
                                <a href="register.php?lang=<?php echo $lang; ?>" class="btn btn-outline-danger">
                                    <i class="fas fa-times me-2"></i>İptal
                                </a>
                            </div>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-3">
                            <a href="login.php?lang=<?php echo $lang; ?>">Zaten hesabınız var mı? Giriş yapın</a>
                        </div>
                        
                        <div class="text-center mt-2">
                            <a href="register.php?lang=tr">Türkçe</a> | 
                            <a href="register.php?lang=en">English</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanım Koşulları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu uygulama kişisel konum verilerinizi kullanır. Verileriniz güvenli bir şekilde saklanır ve üçüncü şahıslarla paylaşılmaz.</p>
                    <p>Uygulamayı kullanarak veri işleme politikamızı kabul etmiş olursunuz.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cinsiyet seçimi
        function selectGender(gender) {
            document.querySelectorAll('.gender-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelector(`[data-gender="${gender}"]`).classList.add('selected');
            document.getElementById('selectedGender').value = gender;
        }

        // Şifre eşleşme kontrolü
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.className = 'form-text';
            } else if (password === confirmPassword) {
                matchText.textContent = '✓ Şifreler eşleşiyor';
                matchText.className = 'form-text text-success';
            } else {
                matchText.textContent = '✗ Şifreler eşleşmiyor';
                matchText.className = 'form-text text-danger';
            }
        });

        // Telefon formatı
        document.querySelector('input[name="cep_telefonu"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = value.substring(1);
            }
            if (value.length > 0) {
                value = value.match(new RegExp('.{1,3}', 'g')).join(' ');
            }
            e.target.value = value;
        });

        // Doğrulama kodu input kontrolü
        const verificationInput = document.querySelector('.verification-input');
        if (verificationInput) {
            verificationInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').substring(0, 6);
            });

            // Odağı doğrulama inputuna ver
            verificationInput.focus();
        }

        // Kod tekrar gönderme
        function resendCode() {
            if (confirm('Doğrulama kodunu tekrar göndermek istiyor musunuz?')) {
                // Burada AJAX ile kod gönderme işlemi yapılabilir
                alert('Yeni doğrulama kodu gönderildi!');
            }
        }

        // Form gönderim kontrolü
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            const gender = document.getElementById('selectedGender').value;
            if (!gender) {
                e.preventDefault();
                alert('Lütfen cinsiyet seçiniz.');
                return false;
            }
        });
    </script>
</body>
</html>
