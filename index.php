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

// ARKADAŞ KONUMLARINI AL - GÜNCELLENMİŞ SORGUSU
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
    k.boylam
FROM arkadasliklar a
JOIN kullanicilar k ON (
    (a.istek_id = :kullanici_id AND a.alici_id = k.id) 
)
WHERE a.durum = 'kabul' 
AND k.enlem IS NOT NULL 
AND k.boylam IS NOT NULL
ORDER BY k.ad ASC
";

$friends_stmt = $db->prepare($friends_query);
$friends_stmt->bindParam(":kullanici_id", $kullanici_id, PDO::PARAM_INT);
$friends_stmt->execute();
$friends_locations = $friends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Varsayılan konum - kullanıcının kendi konumu
$user_lat = !empty($user_profile['enlem']) ? $user_profile['enlem'] : 41.0082;
$user_lon = !empty($user_profile['boylam']) ? $user_profile['boylam'] : 28.9784;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FrendsApp</title>

  <!-- Bootstrap -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <script src="js/bootstrap.bundle.min.js"></script>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Yandex Maps -->
  <script src="https://api-maps.yandex.ru/2.1/?lang=tr_TR" type="text/javascript"></script>

  <style>
    body {
      background-color: #c5a7acff;
      font-family: 'Segoe UI', sans-serif;
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

    /* Ana konteyner */
    .app-container {
      height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Map konteyneri */
    .map-container {
      flex: 1;
      position: relative;
      height: calc(100vh - 60px);
    }

    #map {
      width: 100%;
      height: 103%;
    }

    /* Offcanvas */
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

    /* Özel Pin Tasarımı */
    .custom-pin {
      position: relative;
      width: 50px;
      height: 60px;
    }

    .pin-body {
      position: absolute;
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50% 50% 50% 0;
      transform: rotate(-45deg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      border: 3px solid white;
      overflow: hidden;
    }

    .pin-body::after {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 0;
      border-left: 8px solid transparent;
      border-right: 8px solid transparent;
      border-top: 12px solid #764ba2;
    }

    .pin-image {
      position: absolute;
      top: 6px;
      left: 6px;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      object-fit: cover;
      transform: rotate(45deg);
      border: 2px solid white;
    }

    /* Kendi pinim için farklı renk */
    .my-pin .pin-body {
      background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
    }

    .my-pin .pin-body::after {
      border-top-color: #0097a7;
    }

    /* Arkadaş pinleri için farklı renk */
    .friend-pin .pin-body {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .friend-pin .pin-body::after {
      border-top-color: #20c997;
    }

    /* Balon içeriği için stil */
    .balloon-content {
      padding: 15px;
      min-width: 250px;
    }
    
    .balloon-user-info {
      display: flex;
      align-items: center;
      margin-bottom: 10px;
    }
    
    .balloon-user-img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      margin-right: 15px;
      border: 3px solid #667eea;
    }
    
    .balloon-user-details h5 {
      margin: 0;
      font-size: 18px;
      color: #333;
    }
    
    .balloon-user-details p {
      margin: 5px 0;
      font-size: 14px;
      color: #666;
    }
    
    .balloon-user-details i {
      width: 16px;
      margin-right: 5px;
      color: #667eea;
    }

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

    .navbar-brand {
      position: absolute;
      left: 3%;
      transform: translateX(-6%);
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

    /* Cluster stilleri */
    .ymaps-2-1-79-cluster {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        border: 3px solid white;
        border-radius: 50%;
        color: white;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }

    .ymaps-2-1-79-cluster__content {
        font-size: 14px;
        font-weight: bold;
    }

    /* Yandex Maps Örnek Stilleri */
    .ballon_header {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
        border-bottom: 2px solid #667eea;
        padding-bottom: 5px;
    }

    .ballon_body {
        font-size: 14px;
        line-height: 1.5;
        color: #666;
        margin-bottom: 10px;
    }

    .ballon_footer {
        font-size: 12px;
        color: #888;
        font-style: italic;
        border-top: 1px solid #eee;
        padding-top: 5px;
    }

    /* Sol kolon liste öğeleri */
    .ymaps-2-1-79-balloon__list-item {
        padding: 8px 10px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .ymaps-2-1-79-balloon__list-item:hover {
        background-color: #f5f5f5;
    }

    .ymaps-2-1-79-balloon__list-item_active {
        background-color: #667eea;
        color: white;
    }

    .friend-list-item {
        display: flex;
        align-items: center;
        padding: 8px 5px;
    }

    .friend-list-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 8px;
        border: 2px solid #ddd;
    }

    .friend-list-name {
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 70px;
    }

    /* Sağ kolon detayları */
    .friend-detail-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #667eea;
        margin: 0 auto 15px auto;
        display: block;
    }

    .friend-detail-name {
        text-align: center;
        font-size: 16px;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
    }

    .friend-detail-info {
        font-size: 13px;
        line-height: 1.4;
    }

    .friend-detail-info p {
        margin-bottom: 8px;
        padding: 6px 8px;
        background: #f8f9fa;
        border-radius: 4px;
        border-left: 3px solid #667eea;
    }

    .friend-detail-info i {
        width: 14px;
        margin-right: 6px;
        color: #667eea;
    }

    .friend-detail-info strong {
        color: #495057;
    }

    /* Sadece mobilde (768px ve altı) görünür */
    @media (max-width: 768px) {
      .menu-floating-btn {
        display: flex;
      }

      /* .top-navbar {/*nav üstü çubuğu kapatılıyor
        display: none !important;
      }*/
      
      .location-btn span {
        display: none;
      }
      
      .location-btn {
        padding: 8px;
      }

      .friend-list-name {
        max-width: 60px;
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

  <div class="app-container">
    <!-- Floating Menü Butonu -->
    <button class="menu-floating-btn" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
      <i class="fas fa-bars"></i>
    </button>

    <!-- Harita Konteyneri -->
    <div class="map-container">
      <div id="map"></div>
    </div>
  </div>

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
        <a href="#" class="list-group-item list-group-item-action active " onclick="getCurrentLocation()">
          <i class="fas fa-map-marker-alt"></i> Konumum
        </a>
        <a href="dostlar.php" class="list-group-item list-group-item-action">
          <i class="fas fa-users"></i> Arkadaşlarım
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-compass"></i> Yakınımda
        </a>
        <a href="#" class="list-group-item list-group-item-action">
          <i class="fas fa-user-plus"></i> Arkadaş Bul
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

  <script>
    const userLat = <?php echo $user_lat; ?>;
    const userLon = <?php echo $user_lon; ?>;
    const userName = "<?php echo $user_profile['ad'] . ' ' . $user_profile['soyad']; ?>";
    const userPhone = "<?php echo $user_profile['cep_telefonu']; ?>";
    const userProfileImage = "<?php echo !empty($user_profile['profil_resmi']) ? $user_profile['profil_resmi'] : ''; ?>";
    const userGender = "<?php echo $user_profile['cinsiyet']; ?>";
    const userCity = "<?php echo $user_profile['sehir']; ?>";
    const userId = <?php echo $kullanici_id; ?>;

    // Arkadaş konumlarını PHP'den JavaScript'e aktar
    const friendsLocations = <?php echo json_encode($friends_locations); ?>;

    let map;
    let userPlacemark;
    let friendPlacemarks = [];

    // İki koordinat arasındaki mesafeyi hesaplayan fonksiyon (metre cinsinden)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Dünya'nın yarıçapı (metre)
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c;
        return distance;
    }

    // Arkadaş balon içeriği oluşturma fonksiyonu
    function createFriendBalloonContent(friend) {
        const defaultImage = friend.cinsiyet === 'kadin' 
            ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' 
            : 'https://cdn-icons-png.flaticon.com/512/847/847970.png';
        
        // Mesafe hesapla
        const distance = calculateDistance(
            userLat, 
            userLon, 
            parseFloat(friend.enlem), 
            parseFloat(friend.boylam)
        );
            
        return `
            <div class="balloon-content">
                <div class="balloon-user-info">
                    <img src="${friend.profil_resmi || defaultImage}" 
                         alt="${friend.ad} ${friend.soyad}" 
                         class="balloon-user-img"
                         onerror="this.src='${defaultImage}'">
                    <div class="balloon-user-details">
                        <h5>${friend.ad} ${friend.soyad}</h5>
                        <p><i class="fas fa-phone"></i> ${friend.cep_telefonu}</p>
                        <p><i class="fas fa-map-marker-alt"></i> Enlem: ${friend.enlem}</p>
                        <p>Boylam: ${friend.boylam}</p>
                        <p><i class="fas fa-ruler"></i> ${distance.toFixed(0)} m uzaklıkta</p>
                        <p><i class="fas fa-city"></i> Şehir: ${friend.sehir || 'Belirtilmemiş'}</p>
                    </div>
                </div>
            </div>
        `;
    }

    // Kullanıcı balon içeriği oluşturma fonksiyonu
    function createUserBalloonContent() {
        const defaultImage = userGender === 'kadin' 
            ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' 
            : 'https://cdn-icons-png.flaticon.com/512/847/847970.png';
            
        return `
            <div class="balloon-content">
                <div class="balloon-user-info">
                    <img src="${userProfileImage || defaultImage}" 
                         alt="${userName}" 
                         class="balloon-user-img"
                         onerror="this.src='${defaultImage}'">
                    <div class="balloon-user-details">
                        <h5>${userName} <small>(Siz)</small></h5>
                        <p><i class="fas fa-phone"></i> ${userPhone}</p>
                        <p><i class="fas fa-map-marker-alt"></i> Enlem: ${userLat}, Boylam: ${userLon}</p>
                        <p><i class="fas fa-clock"></i> Son Güncelleme: Şimdi</p>
                    </div>
                </div>
            </div>
        `;
    }

    // Konum güncelleme fonksiyonu
    function getCurrentLocation() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function(position) {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            
            // Haritayı yeni konuma taşı
            map.setCenter([lat, lon]);
            
            // Placemark'ı güncelle
            userPlacemark.geometry.setCoordinates([lat, lon]);
            
            // Konumu veritabanına kaydet
            updateUserLocation(lat, lon);
            
            alert('Konumunuz başarıyla güncellendi!');
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

    // Yandex Map initialization
    ymaps.ready(initMap);

    function initMap() {
        map = new ymaps.Map('map', {
            center: [userLat, userLon],
            zoom: 14,
            controls: ['zoomControl']
        });
        
        // Özel pin içeriği için HTML layout oluştur
        const MyIconContentLayout = ymaps.templateLayoutFactory.createClass(
            '<div class="custom-pin my-pin">' +
                '<div class="pin-body">' +
                    '<img src="{{ properties.profileImage }}" alt="{{ properties.userName }}" class="pin-image" onerror="this.src=\'{{ properties.defaultImage }}\'">' +
                '</div>' +
            '</div>'
        );

        // Arkadaş pin layout'u
        const FriendIconContentLayout = ymaps.templateLayoutFactory.createClass(
            '<div class="custom-pin friend-pin">' +
                '<div class="pin-body">' +
                    '<img src="{{ properties.profileImage }}" alt="{{ properties.userName }}" class="pin-image" onerror="this.src=\'{{ properties.defaultImage }}\'">' +
                '</div>' +
            '</div>'
        );

        // Özel cluster balon içeriği layout'u - Yandex örneğine uygun
        const customItemContentLayout = ymaps.templateLayoutFactory.createClass(
            '<h2 class="ballon_header">{{ properties.balloonContentHeader|raw }}</h2>' +
            '<div class="ballon_body">{{ properties.balloonContentBody|raw }}</div>' +
            '<div class="ballon_footer">{{ properties.balloonContentFooter|raw }}</div>'
        );

        // Sol kolon liste öğesi layout'u
        const customListLayout = ymaps.templateLayoutFactory.createClass(
            '<div class="friend-list-item">' +
                '<img src="{{ properties.profileImage }}" alt="{{ properties.userName }}" class="friend-list-avatar" onerror="this.src=\'{{ properties.defaultImage }}\'">' +
                '<span class="friend-list-name">{{ properties.userName }}</span>' +
            '</div>'
        );

        // Kullanıcının kendi Placemark'ını oluştur
        userPlacemark = new ymaps.Placemark([userLat, userLon], {
            hintContent: userName + ' (Siz)',
            balloonContent: createUserBalloonContent(),
            profileImage: userProfileImage || (userGender === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'),
            userName: userName,
            defaultImage: userGender === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'
        }, {
            iconLayout: 'default#imageWithContent',
            iconImageHref: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
                '<svg width="50" height="60" xmlns="http://www.w3.org/2000/svg">' +
                '<path fill="transparent" d="M0 0h50v60H0z"/>' +
                '</svg>'
            ),
            iconContentSize: [50, 60],
            iconContentOffset: [-25, -60],
            iconContentLayout: MyIconContentLayout,
            hasBalloon: true,
            balloonCloseButton: true,
            balloonPanelMaxMapArea: 0
        });

        map.geoObjects.add(userPlacemark);
        
        // ARKADAŞLARIN PLACEMARK'LARINI OLUŞTUR
        friendsLocations.forEach(friend => {
            if (friend.enlem && friend.boylam) {
                // Mesafe hesapla (metre cinsinden)
                const distance = calculateDistance(
                    userLat, 
                    userLon, 
                    parseFloat(friend.enlem), 
                    parseFloat(friend.boylam)
                );
                
                const friendPlacemark = new ymaps.Placemark(
                    [parseFloat(friend.enlem), parseFloat(friend.boylam)], 
                    {
                        hintContent: friend.ad + ' ' + friend.soyad,
                        balloonContent: createFriendBalloonContent(friend),
                        // Cluster balonunda kullanılacak veriler
                        balloonContentHeader: friend.ad + ' ' + friend.soyad,
                        balloonContentBody: `
                            <div class="friend-detail-info">
                                <img src="${friend.profil_resmi || (friend.cinsiyet === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png')}" 
                                     alt="${friend.ad} ${friend.soyad}" 
                                     class="friend-detail-avatar"
                                     onerror="this.src='${friend.cinsiyet === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'}'">
                                <div class="friend-detail-name">${friend.ad} ${friend.soyad}</div>
                                <p><i class="fas fa-phone"></i> <strong>Telefon:</strong> ${friend.cep_telefonu}</p>
                                <p><i class="fas fa-ruler"></i> <strong>Uzaklık:</strong> ${distance.toFixed(0)} m</p>
                                <p><i class="fas fa-map-marker-alt"></i> <strong>Konum:</strong> ${friend.enlem}, ${friend.boylam}</p>
                                <p><i class="fas fa-city"></i> <strong>Şehir:</strong> ${friend.sehir || 'Belirtilmemiş'}</p>
                            </div>
                        `,
                        balloonContentFooter: 'FrendsApp - Arkadaş Konumu',
                        // Sol kolon için veriler
                        profileImage: friend.profil_resmi || (friend.cinsiyet === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'),
                        userName: friend.ad + ' ' + friend.soyad,
                        userPhone: friend.cep_telefonu,
                        userCity: friend.sehir,
                        userId: friend.id,
                        latitude: friend.enlem,
                        longitude: friend.boylam,
                        distance: distance.toFixed(0),
                        defaultImage: friend.cinsiyet === 'kadin' ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png' : 'https://cdn-icons-png.flaticon.com/512/847/847970.png'
                    }, 
                    {
                        iconLayout: 'default#imageWithContent',
                        iconImageHref: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
                            '<svg width="50" height="60" xmlns="http://www.w3.org/2000/svg">' +
                            '<path fill="transparent" d="M0 0h50v60H0z"/>' +
                            '</svg>'
                        ),
                        iconContentSize: [50, 60],
                        iconContentOffset: [-25, -60],
                        iconContentLayout: FriendIconContentLayout,
                        hasBalloon: true,
                        balloonCloseButton: true,
                        balloonPanelMaxMapArea: 0
                    }
                );
                
                friendPlacemarks.push(friendPlacemark);
            }
        });
        
        // CLUSTER KULLANIMI - Yandex örneğine uygun
        if (friendPlacemarks.length > 0) {
            const clusterer = new ymaps.Clusterer({
                clusterDisableClickZoom: true,
                clusterOpenBalloonOnClick: true,
                // Panel modunda balon açılmasını engelle
                clusterBalloonPanelMaxMapArea: 0,
                // Balon içeriği genişliği
                clusterBalloonContentLayoutWidth: 400,
                // Özel layout'ları kullan
                clusterBalloonItemContentLayout: customItemContentLayout,
                clusterBalloonLeftColumnWidth: 120,
                // Sol kolon için özel layout
                clusterBalloonLeftContentLayout: customListLayout,
                clusterIconLayout: 'default#pieChart',
                clusterIconPieChartRadius: 25,
                clusterIconPieChartCoreRadius: 10,
                clusterIconPieChartStrokeWidth: 3
            });

            clusterer.add(friendPlacemarks);
            map.geoObjects.add(clusterer);
            
            console.log('Cluster oluşturuldu, toplam arkadaş:', friendPlacemarks.length);
        } else {
            console.log('Cluster oluşturulamadı: Arkadaş bulunamadı');
        }

        // Haritayı kullanıcının konumuna odakla
        map.setCenter([userLat, userLon]);
    }

    // Resim yükleme hatalarını yakala
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                if (this.classList.contains('pin-image') || this.src.includes('profil_resmi')) {
                    const defaultImg = userGender === 'kadin' 
                        ? 'https://cdn-icons-png.flaticon.com/512/847/847969.png'
                        : 'https://cdn-icons-png.flaticon.com/512/847/847970.png';
                    this.src = defaultImg;
                }
            });
        });
    });
  </script>

  <script>
document.addEventListener("DOMContentLoaded", function() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const enlem = position.coords.latitude;
            const boylam = position.coords.longitude;
            const hiz = position.coords.speed || 0;
            const yon = position.coords.heading || 0;

            fetch('api/update_location.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `enlem=${enlem}&boylam=${boylam}&hiz=${hiz}&yon=${yon}`
            })
            .then(response => response.json())
            .then(data => {
                console.log("Konum kaydetme sonucu:", data);
            })
            .catch(error => console.error("Hata:", error));
        }, function(error) {
            console.error("Konum alınamadı:", error.message);
        });
    } else {
        console.log("Tarayıcı konum özelliğini desteklemiyor.");
    }
});
</script>

</body>
</html>