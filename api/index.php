<?php
// --- CONFIGURATION ---
$API_KEY = "39884|vCk958zhT2f6uQCBa9BH7wQ7d4HUQRuGaSFUN1TC5edef98a"; 

// --- 1. THE OFFER ROUTING TABLE ---
// Put your preferred Offer IDs in order (Best ones at the top)
$REGION_OFFERS = [
    "US" => [9164, 2993, 15696, 5930], // USA Specific
    
    "EUROPE" => [15696, 5930, 1111, 2222], // UK, FR, DE, IT, etc.
    
    "AFRICA" => [5930, 3333, 4444, 5555], // MA, EG, ZA, NG, etc.
    
    "GLOBAL" => [5930, 2993] // Everyone else
];

// Define which Country Codes belong to which group
$EUROPE_CODES = ['GB', 'FR', 'DE', 'IT', 'ES', 'NL', 'BE', 'SE', 'NO', 'CH'];
$AFRICA_CODES = ['MA', 'EG', 'ZA', 'NG', 'KE', 'DZ', 'TN', 'GH'];

// --- 2. DETECT USER COUNTRY ---
function getUserCountry() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if ($ip == '127.0.0.1' || $ip == '::1') return "US"; // Testing fallback
    $details = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));
    return ($details && $details->status == 'success') ? $details->countryCode : "US";
}

$userCC = getUserCountry();

// Decide which ID list to use
if ($userCC == "US") {
    $targetIds = $REGION_OFFERS["US"];
} elseif (in_array($userCC, $EUROPE_CODES)) {
    $targetIds = $REGION_OFFERS["EUROPE"];
} elseif (in_array($userCC, $AFRICA_CODES)) {
    $targetIds = $REGION_OFFERS["AFRICA"];
} else {
    $targetIds = $REGION_OFFERS["GLOBAL"];
}

// --- 3. FETCH AND FILTER API DATA ---
function getOffers($apiKey, $targetIds) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = urlencode($_SERVER['HTTP_USER_AGENT']);
    $url = "https://applocked.store/api/v2?get_offers=true&ip=$ip&user_agent=$ua";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apiKey"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($res, true);
    $finalList = [];

    if ($data && isset($data['offers'])) {
        // We loop through YOUR target IDs list first to preserve your "Top to Bottom" order
        foreach ($targetIds as $id) {
            foreach ($data['offers'] as $offer) {
                if ($offer['offerid'] == $id) {
                    $finalList[] = $offer;
                    break; 
                }
            }
        }
        
        // If your specific list is empty (offers expired or not available), 
        // fallback to top 4 random available offers so the wall isn't blank
        if (empty($finalList)) {
            return array_slice($data['offers'], 0, 4);
        }
    }
    return $finalList;
}

$myOffers = getOffers($API_KEY, $targetIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Avatar World Rewards - Claim Your Coins Now</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&family=Fredoka+One&family=Oxygen:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

  <style>
    /* --- ORIGINAL DARK CYBER THEME --- */
    :root {
      --primary: #6a11cb;
      --secondary: #2575fc;
      --accent: #22d3ee;
      --darker: #000000;
      --gray: #8a8a8a;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--darker); color: white; overflow-x: hidden; }
    .cyber-grid { position: fixed; inset: 0; background-image: linear-gradient(rgba(106, 17, 203, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(106, 17, 203, 0.1) 1px, transparent 1px); background-size: 50px 50px; z-index: -1; pointer-events: none; }
    header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); }
    .logo { font-family: 'Fredoka One'; font-size: 2rem; color: var(--accent); display: flex; align-items: center; justify-content: center; gap: 10px; }
    .logo img { width: 60px; filter: drop-shadow(0 0 10px var(--accent)); }
    .container { max-width: 1200px; margin: auto; padding: 20px; text-align: center; }
    .rewards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px; }
    .reward-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(106, 17, 203, 0.3); border-radius: 20px; padding: 25px; transition: 0.3s; cursor: pointer; }
    .reward-card:hover { transform: translateY(-5px); border-color: var(--accent); }
    .reward-image { width: 90px; height: 90px; margin-bottom: 15px; }
    .claim-btn { width: 100%; padding: 14px; border-radius: 12px; border: none; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; font-weight: 800; cursor: pointer; }

    /* --- MODAL LOGIC --- */
    .modal { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.95); z-index: 1000; align-items: center; justify-content: center; padding: 15px; }
    .modal-content { background: #0d0d0d; border: 2px solid #333; border-radius: 30px; padding: 30px; max-width: 400px; width: 100%; text-align: center; transition: 0.4s; }
    .locker-item-icon { width: 80px; height: 80px; border-radius: 15px; margin: 0 auto 15px; overflow: hidden; background: #fff; padding: 5px; border: 2px solid #fff; }
    .locker-item-icon img { width: 100%; height: 100%; object-fit: contain; }
    .progress-bar { width: 100%; height: 12px; background: #1a1a1a; border-radius: 50px; margin: 15px 0; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--accent); width: 0%; box-shadow: 0 0 10px var(--accent); }

    /* --- NEOX LOCKER THEME (Step 3) --- */
    .neox-theme {
        background: linear-gradient(180deg, #ff5f6d, #ffc371) !important;
        border: none !important; color: #fff !important; font-family: 'Oxygen', sans-serif !important;
    }
    .neox-title { font-size: 30px; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 2px; }
    .neox-subtitle { font-size: 14px; margin-bottom: 20px; font-weight: 500; }
    .neox-offer-btn {
        display: block; width: 100%; padding: 12px; margin: 10px 0;
        background-image: linear-gradient(to right, #ff78f0, #c194ff, #7da7ff, #42b1fc, #39b5e0);
        color: white; text-decoration: none; font-size: 14px; border-radius: 20px;
        border: 1px solid #F5EA5A; transition: 0.3s; font-weight: 600; text-align: center;
    }
    .neox-footer { margin-top: 20px; background: #F5EA5A; padding: 10px; border-radius: 10px; font-size: 12px; color: #A31ACB; font-weight: 600; animation: neox-pulse 1.5s infinite; }
    @keyframes neox-pulse { 50% { box-shadow: 0 0 10px #000; } }
    .hidden { display: none !important; }
  </style>
</head>
<body>

  <div class="cyber-grid"></div>

  <header>
    <div class="logo">
      <img src="https://i.ibb.co/GvNvTnY3/image-2026-01-20-171756861-removebg-preview.png">
      <span>AVATAR WORLD</span>
    </div>
  </header>

  <div class="container">
    <h1 style="font-family:'Fredoka One'; font-size: 2.5rem; margin-top:20px;">CLAIM REWARDS</h1>
    <div class="rewards-grid">
      <div class="reward-card" onclick="openFlow('150 AW Coins', 'https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg')">
        <img src="https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg" class="reward-image">
        <h3 style="font-family:'Fredoka One';">150 AW Coins</h3>
        <button class="claim-btn">Claim Now</button>
      </div>
      <div class="reward-card" onclick="openFlow('1,250 AW Coins', 'https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg')">
        <img src="https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg" class="reward-image">
        <h3 style="font-family:'Fredoka One';">1,250 AW Coins</h3>
        <button class="claim-btn">Claim Now</button>
      </div>
      <div class="reward-card" onclick="openFlow('7,500 AW Coins', 'https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg')">
        <img src="https://i.ibb.co/m54K55FY/IMG-20250816-WA0002.jpg" class="reward-image">
        <h3 style="font-family:'Fredoka One';">7,500 AW Coins</h3>
        <button class="claim-btn">Claim Now</button>
      </div>
    </div>
  </div>

  <div class="modal" id="flowModal">
    <div class="modal-content" id="m-content">
      <div class="locker-item-icon" id="locker-icon-wrap">
          <img id="m-icon" src="">
      </div>
      <div id="step-1">
        <h2 id="m-name" style="font-family:'Fredoka One'; margin-bottom:20px;"></h2>
        <input type="text" id="username" placeholder="Avatar Username..." style="width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #333; background: #111; color: #fff; margin-bottom: 15px; text-align: center; outline: none;">
        <button class="claim-btn" onclick="goLoad()">CONTINUE</button>
      </div>
      <div id="step-2" class="hidden">
        <h2 id="l-title" style="font-family:'Fredoka One'; color:var(--accent);">CONNECTING...</h2>
        <div class="progress-bar"><div id="p-fill" class="progress-fill"></div></div>
        <div id="p-pct" style="font-weight:900; font-size:24px; color:var(--accent);">0%</div>
        <p id="l-msg" style="font-size:11px; color:var(--gray); margin-top:10px;">Injecting resources...</p>
      </div>
      <div id="step-3" class="hidden">
        <div class="neox-title">NEOX</div>
        <div class="neox-subtitle">Complete one Offer below, and the <b id="final-p"></b> will be downloaded <b>Automatically</b></div>
        <div class="quest-list">
          <?php if(!empty($myOffers)): ?>
            <?php foreach($myOffers as $o): ?>
            <a href="<?php echo $o['link']; ?>" target="_blank" class="neox-offer-btn"><?php echo $o['name_short']; ?></a>
            <?php endforeach; ?>
          <?php else: ?>
            <p>No compatible missions found.</p>
          <?php endif; ?>
        </div>
        <div class="neox-footer">Waiting for completion...</div>
      </div>
    </div>
  </div>

  <script>
    function openFlow(name, img) {
      document.getElementById('m-name').innerText = name;
      document.getElementById('final-p').innerText = name;
      document.getElementById('m-icon').src = img;
      document.getElementById('m-content').classList.remove('neox-theme');
      document.getElementById('locker-icon-wrap').classList.remove('hidden');
      document.getElementById('flowModal').style.display = 'flex';
      document.getElementById('step-1').classList.remove('hidden');
      document.getElementById('step-2').classList.add('hidden');
      document.getElementById('step-3').classList.add('hidden');
    }
    function goLoad() {
      if(document.getElementById('username').value.length < 3) return alert("Enter your username!");
      document.getElementById('step-1').classList.add('hidden');
      document.getElementById('step-2').classList.remove('hidden');
      let bar = document.getElementById('p-fill'), pct = document.getElementById('p-pct'), width = 0;
      let iv = setInterval(() => {
        if(width >= 100) {
          clearInterval(iv);
          setTimeout(() => {
            confetti({particleCount: 150, spread: 70});
            document.getElementById('step-2').classList.add('hidden');
            document.getElementById('locker-icon-wrap').classList.add('hidden'); 
            document.getElementById('m-content').classList.add('neox-theme');
            document.getElementById('step-3').classList.remove('hidden');
          }, 1500);
        } else {
          width++;
          bar.style.width = width + '%';
          pct.innerText = width + '%';
        }
      }, 40);
    }
    window.onclick = function(e) { if(e.target == document.getElementById('flowModal')) document.getElementById('flowModal').style.display = "none"; }
  </script>
</body>
</html>
