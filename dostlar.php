<?php
session_start();
require_once "config/database.php";
require_once "config/functions.php";

$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
$translations = loadLanguage($lang);

if (!isset($_SESSION['kullanici_id'])) {
    header("Location: login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$kullanici_id = $_SESSION['kullanici_id'];

// Kullanıcı bilgilerini al
$user_query = "SELECT * FROM kullanicilar WHERE id = :kullanici_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(":kullanici_id", $kullanici_id);
$user_stmt->execute();
$user_profile = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcının son konumunu al
$user_location_query = "SELECT enlem, boylam FROM konumlar WHERE kullanici_id = :kullanici_id ORDER BY zaman DESC LIMIT 1";
$user_location_stmt = $db->prepare($user_location_query);
$user_location_stmt->bindParam(":kullanici_id", $kullanici_id);
$user_location_stmt->execute();
$user_location = $user_location_stmt->fetch(PDO::FETCH_ASSOC);

$user_lat = $user_location ? $user_location['enlem'] : null;
$user_lon = $user_location ? $user_location['boylam'] : null;

// ARKADAŞ LİSTESİNİ AL - son_gorulme bilgisi ile
$friends_query = "
SELECT DISTINCT
    k.id,
    k.ad,
    k.soyad,
    k.cinsiyet,
    k.sehir,
    k.cep_telefonu,
    k.profil_resmi,
    k.enlem,
    k.boylam,
    k.son_gorulme,
    k.son_giris
FROM arkadasliklar a
JOIN kullanicilar k ON (
    (a.istek_id = :kullanici_id AND a.alici_id = k.id) OR
    (a.alici_id = :kullanici_id2 AND a.istek_id = k.id)
)
WHERE a.durum = 'kabul' 
AND k.id != :kullanici_id3
ORDER BY k.son_gorulme DESC, k.ad ASC
";

$friends_stmt = $db->prepare($friends_query);
$friends_stmt->bindParam(":kullanici_id", $kullanici_id, PDO::PARAM_INT);
$friends_stmt->bindParam(":kullanici_id2", $kullanici_id, PDO::PARAM_INT);
$friends_stmt->bindParam(":kullanici_id3", $kullanici_id, PDO::PARAM_INT);
$friends_stmt->execute();
$friends = $friends_stmt->fetchAll(PDO::FETCH_ASSOC);

// İki koordinat arasındaki mesafeyi hesaplayan fonksiyon (metre cinsinden)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    
    $R = 6371000; // Dünya'nın yarıçapı (metre)
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = 
        sin($dLat/2) * sin($dLat/2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
        sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $R * $c;
    return $distance;
}

// Mesafeyi formatla
function formatDistance($distance) {
    if ($distance === null) return 'Konum yok';
    
    if ($distance < 1000) {
        return round($distance) . ' m';
    } else {
        return round($distance / 1000, 1) . ' km';
    }
}

// Son görülme zamanını formatla
function formatLastSeen($lastSeen) {
    if (!$lastSeen) return 'Hiç görülmedi';
    
    $now = new DateTime();
    $lastSeenDate = new DateTime($lastSeen);
    $diff = $now->diff($lastSeenDate);
    
    if ($diff->y > 0) return $diff->y . ' yıl önce';
    if ($diff->m > 0) return $diff->m . ' ay önce';
    if ($diff->d > 0) return $diff->d . ' gün önce';
    if ($diff->h > 0) return $diff->h . ' saat önce';
    if ($diff->i > 0) return $diff->i . ' dakika önce';
    
    return 'Az önce';
}

// Çevrimiçi durumu kontrol et
function getOnlineStatus($lastSeen) {
    if (!$lastSeen) return ['status' => 'offline', 'text' => 'Çevrimdışı'];
    
    $lastSeenTime = strtotime($lastSeen);
    $now = time();
    $diff = $now - $lastSeenTime;
    
    if ($diff < 300) { // 5 dakika
        return ['status' => 'online', 'text' => 'Çevrimiçi'];
    } elseif ($diff < 3600) { // 1 saat
        return ['status' => 'away', 'text' => 'Yakınlarda'];
    } else {
        return ['status' => 'offline', 'text' => formatLastSeen($lastSeen)];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dostlarım - FrendsApp</title>

  <!-- Bootstrap -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <script src="js/bootstrap.bundle.min.js"></script>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      height: 100vh;
      overflow: hidden;
    }

    /* Navbar Stilleri */
    .top-navbar {
      background: linear-gradient(90deg, #637e4eff, #00bcd4);
      height: 60px;
      padding: 0 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      z-index: 1000;
      position: relative;
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .location-btn {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: white;
      padding: 8px 15px;
      border-radius: 20px;
      font-size: 14px;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .location-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-1px);
    }

    .profile-img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: 2px solid white;
      cursor: pointer;
      object-fit: cover;
    }

    .menu-btn {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
      padding: 8px;
      border-radius: 50%;
      transition: all 0.3s ease;
    }

    .menu-btn:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    .navbar-brand {
      color: white !important;
      font-weight: 600;
      display: flex;
      align-items: center;
    }

    .navbar-brand img {
      height: 40px;
      border-radius: 8px;
      margin-right: 8px;
    }

    /* Ana İçerik */
    .main-content {
      height: calc(100vh - 60px);
      overflow-y: auto;
      padding: 20px;
      background: #f8f9fa;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #e9ecef;
    }

    .page-title {
      font-size: 24px;
      font-weight: 700;
      color: #333;
      margin: 0;
    }

    .friends-count {
      font-size: 14px;
      color: #6c757d;
      background: #e9ecef;
      padding: 5px 12px;
      border-radius: 20px;
    }

    /* Arkadaş Grid */
    .friends-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .friends-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
      }
    }

    @media (max-width: 480px) {
      .friends-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
      }
    }

    /* Arkadaş Kartı */
    .friend-card {
      background: white;
      border-radius: 15px;
      padding: 15px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      cursor: pointer;
      border: 2px solid transparent;
      position: relative;
      overflow: hidden;
    }

    .friend-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      border-color: #667eea;
    }

    .friend-card.active {
      border-color: #667eea;
      background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
    }

    .friend-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #e9ecef;
      margin: 0 auto 12px auto;
      transition: all 0.3s ease;
    }

    .friend-card:hover .friend-avatar {
      border-color: #667eea;
      transform: scale(1.05);
    }

    .friend-name {
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
      line-height: 1.3;
    }

    .friend-city {
      font-size: 12px;
      color: #6c757d;
      margin-bottom: 5px;
    }

    .friend-distance {
      font-size: 11px;
      color: #28a745;
      font-weight: 600;
      margin-bottom: 5px;
      background: #d4edda;
      padding: 3px 8px;
      border-radius: 10px;
      display: inline-block;
    }

    .friend-status {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .status-online {
      background: #d4edda;
      color: #155724;
    }

    .status-away {
      background: #fff3cd;
      color: #856404;
    }

    .status-offline {
      background: #f8d7da;
      color: #721c24;
    }

    /* Harita Konteyneri */
    .map-container {
      position: fixed;
      top: 60px;
      left: 0;
      width: 100%;
      height: calc(100vh - 60px);
      background: white;
      z-index: 999;
      display: none;
    }

    .map-container.active {
      display: block;
      animation: fadeIn 0.3s ease;
    }

    .map-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      height: 60px;
    }

    .map-title {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
    }

    .close-map {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
      padding: 5px;
      border-radius: 50%;
      transition: background 0.3s ease;
    }

    .close-map:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    #friendMap {
      width: 100%;
      height: calc(105vh - 120px);
    }

    /* OFFCANVAS MENU */
    .offcanvas {
      background-color: #ffffff;
      border-right: 1px solid #ddd;
      width: 300px;
    }

    .offcanvas-header {
      border-bottom: 1px solid #eee;
      background: #f7f7f7;
      padding: 15px;
    }

    .offcanvas-body {
      padding: 0;
    }

    .list-group-item {
      font-size: 16px;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      padding: 15px 20px;
      border: none;
      border-bottom: 1px solid #eee;
    }

    .list-group-item i {
      margin-right: 12px;
      font-size: 18px;
      width: 24px;
      text-align: center;
    }

    .list-group-item:hover,
    .list-group-item.active {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    /* Boş Durum */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }

    .empty-state i {
      font-size: 64px;
      margin-bottom: 20px;
      color: #dee2e6;
    }

    .empty-state h3 {
      font-size: 24px;
      margin-bottom: 10px;
      color: #495057;
    }

    .empty-state p {
      font-size: 16px;
      margin-bottom: 20px;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      padding: 10px 20px;
      border-radius: 25px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    /* Animasyon */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Floating Menü Butonu */
    .menu-floating-btn {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #637e4eff, #00bcd4);
      border: none;
      border-radius: 50%;
      color: white;
      font-size: 22px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      display: none;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    /* Sadece mobilde görünür */
    @media (max-width: 768px) {
      .menu-floating-btn {
        display: flex;
      }
      
      .location-btn span {
        display: none;
      }
      
      .location-btn {
        padding: 8px;
      }
    }
  </style>
</head>

<body>
  <!-- Top Navbar -->
  <nav class="top-navbar">
    <div class="nav-left">
      <a class="navbar-brand" href="index.php">
        <img src="icons/logo.png" alt="Logo"> FrendsApp
      </a>
    </div>
    
    <div class="nav-right">
      <button class="location-btn" onclick="getCurrentLocation()">
        <i class="fas fa-location-arrow"></i>
        <span>Konum</span>
      </button>
      <img src="<?php echo !empty($user_profile['profil_resmi']) ? $user_profile['profil_resmi'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png'; ?>" 
           alt="Profil" 
           class="profile-img"
           onerror="this.src='https://cdn-icons-png.flaticon.com/512/847/847969.png'">
      <button class="menu-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
        <i class="fas fa-bars"></i>
      </button>
    </div>
  </nav>

  <!-- Ana İçerik -->
  <div class="main-content">
    <!-- Sayfa Başlığı -->
    <div class="page-header">
      <h1 class="page-title">Dostlarım</h1>
      <span class="friends-count"><?php echo count($friends); ?> arkadaş</span>
    </div>

    <?php if (empty($friends)): ?>
      <!-- Arkadaş Yoksa -->
      <div class="empty-state">
        <i class="fas fa-users"></i>
        <h3>Henüz arkadaşınız yok</h3>
        <p>Arkadaş ekleyerek konumlarını haritada görebilirsiniz.</p>
        <button class="btn btn-primary" onclick="window.location.href='index.php'">
          <i class="fas fa-map-marker-alt"></i> Haritaya Git
        </button>
      </div>
    <?php else: ?>
      <!-- Arkadaş Grid -->
      <div class="friends-grid">
        <?php foreach ($friends as $friend): 
          $defaultImage = $friend['cinsiyet'] === 'kadin' 
            ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' 
            : 'https://cdn-icons-png.flaticon.com/512/847/847970.png';
          
          $statusInfo = getOnlineStatus($friend['son_gorulme']);
          
          // Mesafe hesapla
          $distance = null;
          if ($user_lat && $user_lon && $friend['enlem'] && $friend['boylam']) {
            $distance = calculateDistance($user_lat, $user_lon, $friend['enlem'], $friend['boylam']);
          }
        ?>
          <div class="friend-card" 
               data-friend-id="<?php echo $friend['id']; ?>"
               data-friend-name="<?php echo $friend['ad'] . ' ' . $friend['soyad']; ?>"
               data-friend-lat="<?php echo $friend['enlem']; ?>"
               data-friend-lon="<?php echo $friend['boylam']; ?>"
               data-friend-image="<?php echo $friend['profil_resmi'] ?: $defaultImage; ?>"
               data-friend-phone="<?php echo $friend['cep_telefonu']; ?>"
               data-friend-city="<?php echo $friend['sehir']; ?>"
               data-friend-distance="<?php echo $distance ?: ''; ?>">
            
            <img src="<?php echo $friend['profil_resmi'] ?: $defaultImage; ?>" 
                 alt="<?php echo $friend['ad'] . ' ' . $friend['soyad']; ?>" 
                 class="friend-avatar"
                 onerror="this.src='<?php echo $defaultImage; ?>'">
            
            <div class="friend-name"><?php echo $friend['ad'] . ' ' . $friend['soyad']; ?></div>
            
            <?php if ($friend['sehir']): ?>
              <div class="friend-city"><?php echo $friend['sehir']; ?></div>
            <?php endif; ?>
            
            <?php if ($distance !== null): ?>
              <div class="friend-distance">
                <i class="fas fa-ruler"></i> <?php echo formatDistance($distance); ?>
              </div>
            <?php endif; ?>
            
            <span class="friend-status status-<?php echo $statusInfo['status']; ?>">
              <?php echo $statusInfo['text']; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Harita Konteyneri -->
  <div class="map-container" id="mapContainer">
    <div class="map-header">
      <h3 class="map-title" id="mapTitle">Arkadaş Konumu</h3>
      <button class="close-map" id="closeMap">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div id="friendMap"></div>
  </div>

  <!-- Floating Menü Butonu -->
  <button class="menu-floating-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
    <i class="fas fa-bars"></i>
  </button>

  <!-- OFFCANVAS MENU -->
  <div class="offcanvas offcanvas-end" tabindex="-1" id="sidebar">
    <div class="offcanvas-header">
      <div class="d-flex align-items-center">
        <img src="<?php echo !empty($user_profile['profil_resmi']) ? $user_profile['profil_resmi'] : 'https://cdn-icons-png.flaticon.com/512/847/847969.png'; ?>" 
             width="50" 
             height="50" 
             class="rounded-circle me-3"
             onerror="this.src='https://cdn-icons-png.flaticon.com/512/847/847969.png'">
        <div>
          <h6 class="mb-0"><?php echo $user_profile['ad'] . ' ' . $user_profile['soyad']; ?></h6>
          <small class="text-muted"><?php echo $user_profile['cep_telefonu']; ?></small>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>

    <div class="offcanvas-body">
      <div class="list-group list-group-flush">
        <a href="index.php" class="list-group-item list-group-item-action">
          <i class="fas fa-map-marker-alt"></i> Konumum
        </a>
        <a href="dostlar.php" class="list-group-item list-group-item-action active">
          <i class="fas fa-users"></i> Dostlarım
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-compass"></i> Yakınımda
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-user-plus"></i> Dost Bul
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-bell"></i> Bildirimler
          <span class="badge bg-danger float-end">3</span>
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-cog"></i> Ayarlar
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-moon"></i> Karanlık Mod
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
          <i class="fas fa-sign-out-alt"></i> Çıkış Yap
        </a>
      </div>
    </div>
  </div>

  <!-- Yandex Maps -->
  <script src="https://api-maps.yandex.ru/2.1/?lang=tr_TR" type="text/javascript"></script>

  <script>
    let selectedFriend = null;
    let friendMap = null;
    let friendPlacemark = null;
    const userLat = <?php echo $user_lat ? $user_lat : 'null'; ?>;
    const userLon = <?php echo $user_lon ? $user_lon : 'null'; ?>;

    // Arkadaş kartı tıklama olayı
    document.querySelectorAll('.friend-card').forEach(card => {
      card.addEventListener('click', function() {
        const friendId = this.getAttribute('data-friend-id');
        const friendName = this.getAttribute('data-friend-name');
        const friendLat = this.getAttribute('data-friend-lat');
        const friendLon = this.getAttribute('data-friend-lon');
        const friendImage = this.getAttribute('data-friend-image');
        const friendPhone = this.getAttribute('data-friend-phone');
        const friendCity = this.getAttribute('data-friend-city');
        const friendDistance = this.getAttribute('data-friend-distance');

        // Aktif kartı güncelle
        document.querySelectorAll('.friend-card').forEach(c => c.classList.remove('active'));
        this.classList.add('active');

        // Seçilen arkadaşı kaydet
        selectedFriend = {
          id: friendId,
          name: friendName,
          lat: parseFloat(friendLat),
          lon: parseFloat(friendLon),
          image: friendImage,
          phone: friendPhone,
          city: friendCity,
          distance: friendDistance
        };

        // Sadece haritayı göster
        showFriendOnMap();
      });
    });

    // Haritayı kapat
    document.getElementById('closeMap').addEventListener('click', function() {
      document.getElementById('mapContainer').classList.remove('active');
      document.querySelectorAll('.friend-card').forEach(c => c.classList.remove('active'));
      selectedFriend = null;
    });

    // Arkadaşı haritada göster
    function showFriendOnMap() {
      if (!selectedFriend || !selectedFriend.lat || !selectedFriend.lon) {
        alert('Bu arkadaşın konum bilgisi bulunmuyor.');
        return;
      }

      // Başlık güncelle
      document.getElementById('mapTitle').textContent = selectedFriend.name + ' - Konumu';

      // Harita bölümünü göster
      document.getElementById('mapContainer').classList.add('active');

      // Yandex Maps yükle
      ymaps.ready(initFriendMap);
    }

    // Haritayı başlat
    function initFriendMap() {
      // Harita konteynerını temizle
      document.getElementById('friendMap').innerHTML = '';

      // Yeni harita oluştur
      friendMap = new ymaps.Map('friendMap', {
        center: [selectedFriend.lat, selectedFriend.lon],
        zoom: 15,
        controls: ['zoomControl']
      });

      // Özel pin tasarımı
      const friendIconContentLayout = ymaps.templateLayoutFactory.createClass(
        '<div class="custom-pin" style="position:relative;width:50px;height:60px;">' +
          '<div class="pin-body" style="position:absolute;width:50px;height:50px;background:linear-gradient(135deg, #28a745 0%, #20c997 100%);border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 2px 8px rgba(0,0,0,0.3);border:3px solid white;overflow:hidden;">' +
            '<img src="' + selectedFriend.image + '" alt="' + selectedFriend.name + '" style="position:absolute;top:6px;left:6px;width:38px;height:38px;border-radius:50%;object-fit:cover;transform:rotate(45deg);border:2px solid white;" onerror="this.src=\'' + (selectedFriend.image.includes('kadin') ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png') + '\'">' +
          '</div>' +
        '</div>'
      );

      // Mesafe bilgisini hazırla
      let distanceInfo = '';
      if (selectedFriend.distance) {
        distanceInfo = `<p style="margin:5px 0;font-size:14px;color:#666;">
          <i class="fas fa-ruler" style="width:16px;margin-right:5px;color:#667eea;"></i>${selectedFriend.distance} uzaklıkta
        </p>`;
      }

      // Placemark oluştur
      friendPlacemark = new ymaps.Placemark([selectedFriend.lat, selectedFriend.lon], {
        hintContent: selectedFriend.name,
        balloonContent: `
          <div style="padding:15px;min-width:250px;">
            <div style="display:flex;align-items:center;margin-bottom:10px;">
              <img src="${selectedFriend.image}" alt="${selectedFriend.name}" 
                   style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin-right:15px;border:3px solid #667eea;"
                   onerror="this.src='${selectedFriend.image.includes('kadin') ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'}'">
              <div>
                <h5 style="margin:0;font-size:18px;color:#333;">${selectedFriend.name}</h5>
                <p style="margin:5px 0;font-size:14px;color:#666;">
                  <i class="fas fa-phone" style="width:16px;margin-right:5px;color:#667eea;"></i>${selectedFriend.phone}
                </p>
                ${distanceInfo}
                <p style="margin:5px 0;font-size:14px;color:#666;">
                  <i class="fas fa-city" style="width:16px;margin-right:5px;color:#667eea;"></i>${selectedFriend.city || 'Belirtilmemiş'}
                </p>
              </div>
            </div>
          </div>
        `
      }, {
        iconLayout: 'default#imageWithContent',
        iconImageHref: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
          '<svg width="50" height="60" xmlns="http://www.w3.org/2000/svg">' +
          '<path fill="transparent" d="M0 0h50v60H0z"/>' +
          '</svg>'
        ),
        iconContentSize: [50, 60],
        iconContentOffset: [-25, -60],
        iconContentLayout: friendIconContentLayout,
        hasBalloon: true,
        balloonCloseButton: true
      });

      // Placemark'ı haritaya ekle
      friendMap.geoObjects.add(friendPlacemark);

      // Balonu otomatik aç
      setTimeout(() => {
        friendPlacemark.balloon.open();
      }, 1000);
    }

    // Konum güncelleme fonksiyonu
    function getCurrentLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            // Konumu veritabanına kaydet
            updateUserLocation(lat, lon);
            
            alert('Konumunuz başarıyla güncellendi! Sayfayı yenileyerek arkadaşlarınızın uzaklıklarını görebilirsiniz.');
          },
          function(error) {
            alert('Konum alınamadı: ' + error.message);
          }
        );
      } else {
        alert('Tarayıcınız konum servisini desteklemiyor.');
      }
    }

    // Kullanıcı konumunu veritabanına kaydet
    function updateUserLocation(lat, lon) {
      const formData = new FormData();
      formData.append('enlem', lat);
      formData.append('boylam', lon);
      
      fetch('api/update_location.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          console.error('Konum güncelleme hatası:', data.message);
        }
      })
      .catch(error => {
        console.error('Hata:', error);
      });
    }

    // Sayfa yüklendiğinde
    document.addEventListener('DOMContentLoaded', function() {
      console.log('Dostlar sayfası yüklendi. Toplam arkadaş: <?php echo count($friends); ?>');
      console.log('Kullanıcı konumu:', userLat, userLon);
    });
  </script>
</body>
</html>