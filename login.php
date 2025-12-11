<?php
session_start();
require_once "config/database.php";
require_once "config/functions.php";

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'tr';
$translations = loadLanguage($lang);

$error = '';
$success = '';

// Çıkış mesajını kontrol et
if (isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $success = "Başarıyla çıkış yaptınız. Tekrar görüşmek üzere!";
}

if ($_POST && isset($_POST['cep_telefonu'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $cep_telefonu = preg_replace('/[^0-9]/', '', $_POST['cep_telefonu']);
    $sifre = $_POST['sifre'];
    
    // Debug için
    error_log("Giriş denemesi: $cep_telefonu");
    
    if (empty($cep_telefonu) || empty($sifre)) {
        $error = "Telefon numarası ve şifre gereklidir.";
    } else {
        $query = "SELECT * FROM kullanicilar WHERE cep_telefonu = :cep_telefonu AND aktif = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":cep_telefonu", $cep_telefonu);
        
        if ($stmt->execute()) {
            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug için
                error_log("Kullanıcı bulundu: " . $row['ad'] . " " . $row['soyad']);
                error_log("Girilen şifre: $sifre");
                error_log("Hashlenmiş şifre: " . $row['sifre']);
                
                // Şifre kontrolü
                if (password_verify($sifre, $row['sifre'])) {
                    $_SESSION['kullanici_id'] = $row['id'];
                    $_SESSION['kullanici_adi'] = $row['ad'] . ' ' . $row['soyad'];
                    $_SESSION['cep_telefonu'] = $row['cep_telefonu'];
                    $_SESSION['lang'] = $lang;
                    
                    // Son giriş zamanını güncelle
                    $update_query = "UPDATE kullanicilar SET son_giris = NOW() WHERE id = :id";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bindParam(":id", $row['id']);
                    $update_stmt->execute();
                    
                    error_log("Giriş başarılı, yönlendiriliyor...");
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Telefon numarası veya şifre hatalı!";
                    error_log("Şifre hatalı");
                }
            } else {
                $error = "Bu telefon numarasına kayıtlı aktif kullanıcı bulunamadı!";
                error_log("Kullanıcı bulunamadı veya aktif değil");
            }
        } else {
            $error = "Veritabanı hatası! Lütfen tekrar deneyin.";
            error_log("Sorgu çalıştırma hatası");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations['login']; ?> - <?php echo $translations['site_title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="fas fa-users"></i> <?php echo $translations['site_title']; ?></h2>
                <p class="mb-0"><?php echo $translations['login']; ?></p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="form-floating">
                        <input type="tel" class="form-control" id="cep_telefonu" name="cep_telefonu" 
                               placeholder="<?php echo $translations['phone']; ?>" 
                               value="<?php echo isset($_POST['cep_telefonu']) ? htmlspecialchars($_POST['cep_telefonu']) : ''; ?>" 
                               required>
                        <label for="cep_telefonu"><?php echo $translations['phone']; ?></label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="sifre" name="sifre" 
                               placeholder="<?php echo $translations['password']; ?>" required>
                        <label for="sifre"><?php echo $translations['password']; ?></label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-login btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            <?php echo $translations['login']; ?>
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <a href="register.php?lang=<?php echo $lang; ?>" class="text-decoration-none">
                        <?php echo $translations['register']; ?>
                    </a>
                </div>

                <div class="text-center mt-3">
                    <div class="btn-group" role="group">
                        <a href="?lang=tr" class="btn btn-sm btn-outline-secondary <?php echo $lang == 'tr' ? 'active' : ''; ?>">Türkçe</a>
                        <a href="?lang=en" class="btn btn-sm btn-outline-secondary <?php echo $lang == 'en' ? 'active' : ''; ?>">English</a>
                    </div>
                </div>                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Telefon numarası formatlama
        document.getElementById('cep_telefonu').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = value.substring(1);
            }
            if (value.length > 0) {
                value = value.match(new RegExp('.{1,3}', 'g')).join(' ');
            }
            e.target.value = value;
        });

        // Form gönderim kontrolü
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('cep_telefonu').value.replace(/\D/g, '');
            const password = document.getElementById('sifre').value;
            
            if (phone.length < 10) {
                e.preventDefault();
                alert('Lütfen geçerli bir telefon numarası giriniz.');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Şifre en az 6 karakter olmalıdır.');
                return false;
            }
        });

        // Hata varsa inputları işaretle
        <?php if ($error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('cep_telefonu').classList.add('is-invalid');
                document.getElementById('sifre').classList.add('is-invalid');
            });
        <?php endif; ?>
    </script>
</body>
</html>
