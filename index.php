<?php
$dataFile = "game_data.json";

/* DEFAULT STRUCTURE */
$defaultData = [
    "fields_value" => 150,
    "sell_field_value" => 100,
    "money" => 500,
    "fields" => [],
    "inventory" => ["wheat"=>0,"corn"=>0,"carrot"=>0],
    "prices" => ["wheat"=>2,"corn"=>3,"carrot"=>4],
    "trend"=>"stable",
    "bank" => ["balance"=>0,"loan"=>0,"loan_due_day"=>0],
    "contracts" => ["active"=>null,"history"=>[]],
    "start_time" => time()
];

/* CREATE FILE IF NOT EXISTS */
if(!file_exists($dataFile)){
    file_put_contents($dataFile,json_encode($defaultData));
}

/* READ FILE */
$data = json_decode(file_get_contents($dataFile), true);
if(!$data){
    $data = $defaultData;
    file_put_contents($dataFile,json_encode($defaultData));
}

/* AUTO FIX MISSING KEYS */
$data = array_replace_recursive($defaultData, $data);

// ==================== TAX DAY ====================
if(!isset($data['last_tax_day'])){
    $data['last_tax_day'] = 0; // <=== هنا كتضيفو
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mini Farm ULTRA</title>
<style>
body{
    margin:0; font-family:Segoe UI; color:white; overflow-x:hidden; transition:background 2s;
    background:linear-gradient(#87CEEB,#dff6ff);
}
/* ===== TOP BAR ===== */
.topBar{
    display:flex; justify-content:space-between; align-items:center; padding:15px 30px;
    background:rgba(0,0,0,0.35); backdrop-filter:blur(8px);
}
.money{ background:gold; color:black; padding:8px 20px; border-radius:20px; font-weight:bold; }
.inventory, .prices, .bankBox{ font-size:14px; }

/* ===== FIELDS ===== */
.fieldsContainer{ margin-top:180px; display:flex; flex-wrap:wrap; justify-content:center; gap:15px; }
.field{
    width:120px; height:120px; background:linear-gradient(#8B4513,#5a2d0c);
    border-radius:15px; padding:10px; text-align:center;
    box-shadow:0 8px 20px rgba(0,0,0,0.4);
    display:flex; flex-direction:column; justify-content:center; align-items:center;
}
button{ padding:6px 12px; border:none;border-radius:8px; cursor:pointer;font-weight:bold; margin-top:5px; }

/* ===== SUN & MOON ===== */
.skyObject{ position:absolute; width:110px;height:110px; border-radius:50%; transition:all 1s linear; top:250px; }
#sun{background:radial-gradient(circle,yellow,orange);box-shadow:0 0 40px yellow;}
#moon{background:radial-gradient(circle,#fff,#ccc);box-shadow:0 0 25px white;opacity:0;}
/* ================= DATE BOX ================= */
/* ================= DATE BOX CENTER ================= */
.dateBox {
    position: fixed;           /* يبقى ظاهر فوق كلشي */
    top: 20px;                 /* بعيد شوية على الأعلى */
    left: 50%;                 /* نص الشاشة */
    transform: translateX(-50%); /* باش يكون فعلاً فالنص */
    background: rgba(0,0,0,0.45);  /* شفاف شوي */
    backdrop-filter: blur(8px);     /* تأثير بلور */
    color: #fff;
    font-weight: bold;
    font-size: 18px;
    padding: 12px 25px;
    border-radius: 20px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    z-index: 1000;
    text-align: center;
    min-width: 160px;
}

/* تأثير عند المرور بالفأرة */
.dateBox:hover {
    transform: translateX(-50%) scale(1.05);
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
    background: rgba(0,0,0,0.55);
}
/* Harvest animation */
.harvest-anim {
    animation: harvestScale 0.4s ease;
}

@keyframes harvestScale {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(0); opacity: 0; }
}

/* Floating reward text */
.floating-reward {
    position: absolute;
    color: gold;
    font-weight: bold;
    animation: floatUp 1s ease forwards;
    pointer-events: none;
}

@keyframes floatUp {
    0% { transform: translateY(0); opacity: 1; }
    100% { transform: translateY(-40px); opacity: 0; }
}
</style>
</head>
<body>
<div class="skyObject" id="sun"></div>
<div class="skyObject" id="moon"></div>

<div class="topBar">
    <div>
        <div class="money">$ <span id="money"><?= $data['money'] ?></span></div>
        <div class="prices"> 🌾 Wheat: $<span id="pw"><?= $data['prices']['wheat'] ?></span> 
            🌽 Corn: $<span id="pc"><?= $data['prices']['corn'] ?></span> 
            🥕 Carrot: $<span id="pr"><?= $data['prices']['carrot'] ?></span> 
            Trend: <span id="trend"><?= $data['trend'] ?></span>
        </div>
    
    </div>
    <div class="inventory"> 🌾 <span id="wheat"><?= $data['inventory']['wheat'] ?></span>kg 
        🌽 <span id="corn"><?= $data['inventory']['corn'] ?></span>kg 
        🥕 <span id="carrot"><?= $data['inventory']['carrot'] ?></span>kg
    </div>
</div>
<div style="text-align:center;">
    <!-- عرض البنك والقرض -->

<!-- زر القرض -->
    <button onclick="buyField()">Buy Field (150$)</button>
    <button onclick="sellField()">Sell One Field (100$)</button>
    <button onclick="harvestAll()">Harvest All</button>
    <button onclick="sellAllCrops()">Sell All Crops</button>
</div>
<button id="withdrawBtn">Withdraw $0.10</button>
<div style="text-align:center;">
<div class="dateBox" id="date"></div>
</div>
<div class="fieldsContainer" id="fields"></div>
<script>
// ==================== Game Data ====================//
let gameData = <?= json_encode($data) ?>;
if (typeof gameData.currentDay === "undefined" || gameData.currentDay === null) {
    gameData.currentDay = 1;
}
// 🏷️ تحديد أسعار كل محصول
gameData.prices = {
    wheat: 5,   // 🌾 القمح بـ 3$ لكل كيلو
    corn: 6,    // 🌽 الذرة بـ 5$ لكل كيلو
    carrot: 7   // 🥕 الجزر بـ 4$ لكل كيلو
};
gameData.market = gameData.market || {
    wheat: gameData.prices.wheat,
    corn: gameData.prices.corn,
    carrot: gameData.prices.carrot
};
const GROW_TIME = 120000; // 2 دقائق بالـ ms

// ==================== FIELDS ====================
function renderFields() {
    const container = document.getElementById("fields");
    if (!container) return;
    
    container.innerHTML = "";

    gameData.fields.forEach((field, index) => {
        const div = document.createElement("div");
        div.className = "field";
        div.id = `field-${index}`;

        if (!field.crop) {
            div.innerHTML = `
                <label>Crop:</label><br>
                <select onchange="plant(${index}, this.value)">
                    <option value="" disabled selected>-- Select --</option>
                    <option value="wheat">🌾 Wheat</option>
                    <option value="corn">🌽 Corn</option>
                    <option value="carrot">🥕 Carrot</option>
                </select>
            `;
        } else {
            const elapsed = Date.now() - field.plantTime;
            const percent = Math.min(100, Math.floor((elapsed / GROW_TIME) * 100));

            div.innerHTML = `
                <div class="status">${percent >= 100 ? "✅ Ready" : "🌱 Growing"}</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${percent}%"></div>
                </div>
            `;

            if (percent >= 100) {
                const btn = document.createElement("button");
                btn.innerText = "Harvest";
                btn.onclick = () => harvest(index);
                div.appendChild(btn);
            }
        }
        container.appendChild(div);
    });
}

function buyField() {
    if (gameData.money >= gameData.fields_value && gameData.fields.length < 21) {
        gameData.money -= gameData.fields_value;
        gameData.fields.push({ crop: null, plantTime: null });
        updateUI();
        renderFields();
        saveGame();
        showNotification("🟢 Bought a new field success!", "success");
    } else {
        showNotification("⚠ Not enough money to buy or You have max of fields!", "warning");
    }
}

function sellField() {
    if (gameData.fields.length > 0) {
        gameData.fields.pop();
        gameData.money += gameData.sell_field_value;
        updateUI();
        renderFields();
        saveGame();
        showNotification("Sell a field success!", "success");
    } else {
        showNotification("⚠ No fields to sell!", "warning");
    }
}

function sellAllCrops() {
    let totalEarned = 0;
    for (let crop in gameData.inventory) {
        let amount = gameData.inventory[crop];
        if (amount > 0) {
           let pricePerUnit = gameData.market?.[crop] || gameData.prices[crop] || 2; // كيجيب الثمن من prices، وإلا 2$ كـ fallback
            totalEarned += amount * pricePerUnit;
            gameData.inventory[crop] = 0;
        }
    }
    if (totalEarned > 0) {
        gameData.money += totalEarned;
        updateUI();
        saveGame();
        showNotification(`💰 Sold all crops for $${totalEarned}`, "success");
    } else {
        showNotification("⚠ No crops to sell", "warning");
    }
}

function plant(index, type) {

    if (gameData.workersStopped) {
        showNotification("🚫 Workers are on strike!", "error");
        return;
    }

    gameData.fields[index].crop = type;
    gameData.fields[index].plantTime = Date.now();

    // 🔢 نحسبو العمليات ديال اليوم
    gameData.todayPlantCount = (gameData.todayPlantCount || 0) + 1;

    renderFields();
    saveGame();
}

function harvest(index) {

    if (gameData.workersStopped) {
        showNotification("🚫 Workers are on strike!", "error");
        return;
    }

    const fieldElement = document.querySelectorAll(".field")[index];
    const crop = gameData.fields[index].crop;

    if (!crop) return;

    if (!gameData.inventory[crop]) gameData.inventory[crop] = 0;
    gameData.inventory[crop] += 10;

    // 🔢 نحسب الحصاد
    gameData.todayHarvestCount = (gameData.todayHarvestCount || 0) + 1;

    playHarvestAnimation(fieldElement, 10);

    setTimeout(() => {
        gameData.fields[index] = { crop: null, plantTime: null };
        updateUI();
        renderFields();
        saveGame();
    }, 600);
}
function harvestAll() {
    let totalHarvested = 0;

    gameData.fields.forEach((field, index) => {

        if (!field.crop) return;
        if (field.plantTime == null) return;

        const elapsed = Date.now() - field.plantTime;

        if (elapsed >= GROW_TIME) {
    if (gameData.workersStopped) return;

            const fieldElement = document.querySelectorAll(".field")[index];

            const cropType = field.crop;

            if (!gameData.inventory[cropType]) {
                gameData.inventory[cropType] = 0;
            }

            gameData.inventory[cropType] += 10;
    gameData.todayHarvestCount = (gameData.todayHarvestCount || 0) + 1;

            // 🔥 Animation لكل حقل
            playHarvestAnimation(fieldElement, 10);

            gameData.fields[index] = { crop: null, plantTime: null };
            totalHarvested += 10;
        }
    });

    if (totalHarvested > 0) {
        setTimeout(() => {
            updateUI();
            renderFields();
            saveGame();
            showNotification(`🌾 Harvested ${totalHarvested}!`, "success");
        }, 600);
    } else {
        showNotification("⚠ No crops ready to harvest", "warning");
    }
}
function playHarvestAnimation(element, amount) {
    element.classList.add("harvest-anim");

    const reward = document.createElement("div");
    reward.className = "floating-reward";
    reward.innerText = "+$" + amount;

    element.appendChild(reward);

    setTimeout(() => {
        element.classList.remove("harvest-anim");
        reward.remove();
    }, 600);
}
// ==================== UI ====================
function updateUI() {
    const elements = ["money", "wheat", "corn", "carrot"];
    elements.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.innerText = id === "money" ? gameData.money : (gameData.inventory[id] || 0);
        }
    });
}

// ==================== TIME & ENVIRONMENT ====================
const SECONDS_PER_REAL_SECOND = 120; // 1s real = 2 minutes in game
function updateTime() {
    if (!gameData) return;

    const now = Date.now();
    // حساب الفرق الزمني الحقيقي (ثواني)
    const realElapsed = (now - (gameData.last_real_time || now)) / 1000; 
    // تحويله لوقت اللعبة (مثلاً كل ثانية حقيقية تعادل X ثانية فالعالم الافتراضي)
    const gameElapsed = realElapsed * (typeof SECONDS_PER_REAL_SECOND !== 'undefined' ? SECONDS_PER_REAL_SECOND : 120);

    // تحديث الوقت التراكمي
    gameData.game_seconds = (gameData.game_seconds || 0) + gameElapsed;

    // حساب الأيام والساعات والدقائق
    const day = Math.floor(gameData.game_seconds / 86400) + 1;
    const hours = Math.floor((gameData.game_seconds % 86400) / 3600);
    const minutes = Math.floor((gameData.game_seconds % 3600) / 60);

    // تحديث الواجهة
    const timeDisplay = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    document.getElementById("date").innerText = `Day ${day} | ${timeDisplay}`;
resetBuyerMessagesIfNewDay(day);
maybeSendBuyerMessage();
    applyDailyTax(day);
// ✅ نطبق العمال وندير reset للدعم الحكومي فقط إلا تبدل اليوم
    if (gameData.currentDay !== day) {
        // reset support flags يوميا
        gameData.taxSupportGiven = false;
        gameData.workerSupportGiven = false;

        applyWorkerCost(day);
        updateMarket();
        gameData.currentDay = day;
    }
        updateEnvironment(hours, minutes);
    // حفظ آخر وقت حقيقي تم فيه التحديث
    gameData.last_real_time = now;
}

function updateEnvironment(hours, minutes) {
    const sun = document.getElementById("sun");
    const moon = document.getElementById("moon");
    const body = document.body;

    const currentMinute = (hours * 60) + minutes;
    const percent = currentMinute / (24 * 60); // نسبة اليوم كامل

    const width = window.innerWidth - 120;
    const posX = percent * width;
    const posY = 250 - (180 * Math.sin(Math.PI * percent));

    // تحريك الشمس والقمر
    [sun, moon].forEach(obj => {
        if (obj) {
            obj.style.left = `${posX}px`;
            obj.style.top = `${posY}px`;
        }
    });

    // تغيير ألوان السماء
    if (hours >= 6 && hours < 18) {
        const t = (currentMinute - 360) / 720; // 0 فـ 6 الصباح و 1 فـ 6 لعشية

        const rTop = Math.floor(135 * (1 - t) + 135 * t);
        const gTop = Math.floor(206 * (1 - t) + 206 * t);
        const bTop = Math.floor(235 * (1 - t) + 255 * t);

        const rBottom = Math.floor(223 * (1 - t) + 255 * t);
        const gBottom = Math.floor(246 * (1 - t) + 200 * t);
        const bBottom = Math.floor(255 * (1 - t) + 100 * t);

        body.style.background = `linear-gradient(rgba(${rTop},${gTop},${bTop},1), rgba(${rBottom},${gBottom},${bBottom},1))`;
        if (sun) sun.style.opacity = 1;
        if (moon) moon.style.opacity = 0;
    } else {
        body.style.background = "linear-gradient(#001848, #000)";
        if (sun) sun.style.opacity = 0;
        if (moon) moon.style.opacity = 1;
    }
}
function updateMarket(){

    const oldMarket = {...gameData.market};

    gameData.market.wheat = 4 + Math.floor(Math.random()*4);
    gameData.market.corn = 5 + Math.floor(Math.random()*4);
    gameData.market.carrot = 6 + Math.floor(Math.random()*4);

    document.getElementById("pw").innerText = gameData.market.wheat;
    document.getElementById("pc").innerText = gameData.market.corn;
    document.getElementById("pr").innerText = gameData.market.carrot;

    let trendIcon = "➡";

    if(gameData.market.wheat > oldMarket.wheat) trendIcon = "📈";
    if(gameData.market.wheat < oldMarket.wheat) trendIcon = "📉";

    document.getElementById("trend").innerText = trendIcon;

    showNotification("📊 Market prices updated!", "success");
}
// ==================== PROGRESS BARS ====================
function updateProgressBars() {
    gameData.fields.forEach((field, index) => {
        if (!field.crop || !field.plantTime) return;

        const fieldDiv = document.getElementById(`field-${index}`);
        if (!fieldDiv) return;

        const elapsed = Date.now() - field.plantTime; // real time
        const percent = Math.min(100, (elapsed / GROW_TIME) * 100);

        const fill = fieldDiv.querySelector(".progress-fill");
        if (fill) fill.style.width = `${percent}%`;

        if (percent >= 100) {
            const status = fieldDiv.querySelector(".status");
            if (status) status.innerText = "✅ Ready";

            if (!fieldDiv.querySelector("button")) {
                const btn = document.createElement("button");
                btn.innerText = "Harvest";
                btn.className = "harvest-btn";
                btn.onclick = () => harvest(index);
                fieldDiv.appendChild(btn);
            }
        }
    });
    // loop continuously
    requestAnimationFrame(updateProgressBars);
}

// ==================== TAX SYSTEM ====================
function applyDailyTax(day){
    if(!gameData.last_tax_day || day - gameData.last_tax_day >= 2){
        let taxAmount = Math.floor(gameData.money * 0.15);

        if(gameData.money <= 0 && !gameData.taxSupportGiven){
            const supportAmount = 130;
            gameData.money += supportAmount;
            showNotification(`🏛 Government support: +$${supportAmount}`, "success");
            gameData.taxSupportGiven = true; // مرة وحدة فقط
        } else if(gameData.money < taxAmount){
            taxAmount = gameData.money;
            gameData.money = 0;
            showNotification(`⚠ Government took all your money: $${taxAmount}`, "warning");
        } else {
            gameData.money -= taxAmount;
            showNotification(`🏛 Government took 15% tax: $${taxAmount}`, "warning");
        }

        gameData.last_tax_day = day;
        updateUI();
        saveGame();
    }
}
// ==================== NOTIFICATIONS ====================//
const notificationStack = []; // كل notifications نشوفوهم هنا

function showNotification(text, type="success", duration=3000, buttons=[]) {
    let div = document.createElement("div");
    div.className = `notification ${type}`;
    div.style.position = "fixed";
    div.style.right = "-350px";
    div.style.padding = "12px 20px";
    div.style.background = type==="success"?"#4caf50":type==="warning"?"#ff9800":type==="error"?"#f44336":"#2196f3";
    div.style.color="#fff";
    div.style.fontWeight="bold";
    div.style.borderRadius="8px";
    div.style.boxShadow="0 4px 12px rgba(0,0,0,0.2)";
    div.style.zIndex=9999;
    div.style.transition="right 0.5s ease, opacity 0.5s ease";
    div.style.opacity=0;

    div.innerHTML = `<div>${text}</div>`;

    // إذا جاو buttons
    buttons.forEach(btn=>{
        const button = document.createElement("button");
        button.innerText = btn.label;
        button.style.marginLeft = "10px";
        button.onclick = () => {
            btn.onClick();
            removeNotification(div);
        };
        div.appendChild(button);
    });

    document.body.appendChild(div);
    notificationStack.push(div);

    updateNotificationPositions();

    setTimeout(()=>{div.style.right="20px"; div.style.opacity=1;},50);

    if(duration>0){ // إذا duration > 0، تتحيد أوتوماتيكياً
        setTimeout(()=>{removeNotification(div);}, duration);
    }
}

function removeNotification(div){
    div.style.right="-350px";
    div.style.opacity=0;
    setTimeout(()=>{
        div.remove();
        const index = notificationStack.indexOf(div);
        if(index>-1) notificationStack.splice(index,1);
        updateNotificationPositions();
    },500);
}

function updateNotificationPositions(){
    notificationStack.forEach((div,i)=>{
        div.style.top = `${20 + i*70}px`; // كل notification 70px تحت اللي قبلها
    });
}
// ==================== RANDOM BUYER SYSTEM ==================== //
const BUYER_MSGS_PER_DAY = 6;
let buyerMessagesToday = 0;

function getRandomCrop() {
    const crops = ["wheat", "corn", "carrot"];
    return crops[Math.floor(Math.random() * crops.length)];
}

function getRandomAmount() {
    return Math.floor(Math.random() * 20) + 5; // 5-25 kg
}

function sendBuyerMessage() {
    if(buyerMessagesToday >= BUYER_MSGS_PER_DAY) return;

    const crop = getRandomCrop();
    const amount = getRandomAmount();
const price = gameData.market[crop] || gameData.prices[crop] || 2;
    const msgId = Date.now();

    showBuyerNotification(crop, amount, price, msgId);
    buyerMessagesToday++;
}
function updateBuyerPositions() {
    const buyerNotifications = document.querySelectorAll(".notification.buyer");
    buyerNotifications.forEach((div, i) => {
        div.style.top = `${20 + i * 70}px`; // كل وحدة 70px تحت الأخرى
    });
}

function showBuyerNotification(crop, amount, price, msgId){
    const specials = ["normal", "bonus", "urgent"];
    const type = specials[Math.floor(Math.random() * specials.length)];
    let basePrice = gameData.market?.[crop] || gameData.prices[crop] || 2; 
    let extraText = "";
    let icon = "🛒"; 
    if(type === "bonus") { 
        basePrice += 5; 
        extraText = "Bonus offer!"; 
        icon="🌟"; 
    } else if(type === "urgent") { 
        extraText = "Urgent, act fast!"; 
        icon="⚡"; 
    }
    price = basePrice;

    // استدعاء showNotification مع أزرار Accept/Reject
    showNotification(
        `${icon} Buyer wants ${amount}kg of ${crop} for $${price}/kg ${extraText ? `(${extraText})` : ""}`,
        "info",
        0, // تبقى حتى تضغط الزر
        [
            {label: "Accept", onClick: ()=>{
                if(gameData.inventory[crop] >= amount){
                    gameData.inventory[crop] -= amount;
                    const earned = amount * price;
                    gameData.money += earned;
                    updateUI();
                    showNotification(`💰 Sold ${amount}kg ${crop} for $${earned}`, "success");
                } else {
                    showNotification(`⚠ Not enough ${crop} to sell!`, "warning");
                }
            }},
            {label: "Reject", onClick: ()=>{
                showNotification(`❌ You rejected the buyer's offer`, "warning");
            }}
        ]
    );
}
function resetBuyerMessagesIfNewDay(day) {
    if(gameData.last_buyer_reset_day !== day){
        buyerMessagesToday = 0;
        gameData.last_buyer_reset_day = day;
    }
}
// reset daily messages at new day
function resetBuyerMessages() {
    buyerMessagesToday = 0;
}
function maybeSendBuyerMessage() {
    if(buyerMessagesToday < BUYER_MSGS_PER_DAY && Math.random() < 0.02){
        sendBuyerMessage();
    }
}
// check every minute if we need a buyer
setInterval(()=>{
    if(buyerMessagesToday < BUYER_MSGS_PER_DAY && Math.random() < 0.05){
        sendBuyerMessage();
    }
}, 60*1000);
function applyWorkerCost(day) {
    const planted = gameData.todayPlantCount || 0;
    const harvested = gameData.todayHarvestCount || 0;

    if (planted === 0 && harvested === 0) {
        gameData.last_worker_day = day;
        return;
    }

    const landCount = gameData.fields.length;
    const baseCost = landCount * 10;
    const plantCost = planted * 20;
    const harvestCost = harvested * 24;
    const totalCost = baseCost + plantCost + harvestCost;

    let remainingCost = totalCost;

    // 💰 إلا كان عندو فلوس كافية
    if (gameData.money >= totalCost) {

        gameData.money -= totalCost;
        showNotification(`👨‍🌾 Workers salary: -$${totalCost}`, "warning");

        gameData.unpaidDays = 0;
        gameData.workersStopped = false; // 🔥 يرجعو يخدمو

    } else {

        // 💸 يخلص باللي عندو
        remainingCost -= gameData.money;
        const paid = gameData.money;
        gameData.money = 0;

        // 🏛 دعم حكومي مرة وحدة فاليوم
        if (!gameData.workerSupportGiven) {
            const supportAmount = 130;
            gameData.money += supportAmount;
            showNotification(`🏛 Government support for workers: +$${supportAmount}`, "success");
            gameData.workerSupportGiven = true;
        }

        // نحاولو نخلصو بالباقي
        if (gameData.money >= remainingCost) {

            gameData.money -= remainingCost;
            showNotification(`👨‍🌾 Workers salary completed after support`, "warning");

            gameData.unpaidDays = 0;
            gameData.workersStopped = false;

        } else {

            const paidAfterSupport = gameData.money;
            gameData.money = 0;

            showNotification(`⚠ Workers unpaid! Missing $${remainingCost - paidAfterSupport}`, "error");

            gameData.unpaidDays = (gameData.unpaidDays || 0) + 1;

            if (gameData.unpaidDays >= 3) {
                gameData.workersStopped = true;
                showNotification("🚫 Workers stopped working!", "error");
            }
        }
    }

    gameData.todayPlantCount = 0;
    gameData.todayHarvestCount = 0;
    gameData.last_worker_day = day;

    updateUI();
    saveGame();
}

// تهيئة flags عند بداية يوم جديد
function newDayReset(day){
    gameData.taxSupportGiven = false;
    gameData.workerSupportGiven = false;
}
document.getElementById("withdrawBtn").onclick = () => {
    // فتح رابط PayPal.me فالصفحة جديدة
    window.open("https://www.paypal.me/BadreAmrouss/0.10", "_blank");
};
// ==================== SAVE ====================
async function saveGame() {
    try {
        const res = await fetch("save.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(gameData)
        });
        if(!res.ok) console.error("Save failed");
    } catch(err) {
        console.error("Error saving game:", err);
    }
}
// ==================== INIT & TIMER ====================
setInterval(updateTime, 1000);

// نخلي progress يخدم بوحدو
updateProgressBars();
document.addEventListener("DOMContentLoaded", () => {

    if(!gameData.last_real_time){
        gameData.last_real_time = Date.now();
    }

    gameData.game_seconds = gameData.game_seconds || 0;

    if (gameData.fields.length === 0) {
        gameData.fields.push({ crop: null, plantTime: null });
    }

    updateUI();
    renderFields();
    
});
</script>
</body>
</html>