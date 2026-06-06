<?php
http_response_code(503);
header("Retry-After: 3600");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance Mode</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
body{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#0f172a,#1e293b,#334155);
    font-family:Arial,sans-serif;
    overflow:hidden;
}

.bg-circle{
    position:absolute;
    border-radius:50%;
    background:rgba(255,255,255,.05);
    animation:float 8s infinite ease-in-out;
}

.circle1{
    width:300px;
    height:300px;
    top:-100px;
    left:-100px;
}

.circle2{
    width:250px;
    height:250px;
    bottom:-80px;
    right:-80px;
    animation-delay:2s;
}

@keyframes float{
    0%,100%{transform:translateY(0px);}
    50%{transform:translateY(20px);}
}

.maintenance-card{
    max-width:650px;
    width:95%;
    background:rgba(255,255,255,.08);
    backdrop-filter:blur(15px);
    border:1px solid rgba(255,255,255,.15);
    border-radius:25px;
    color:#fff;
    text-align:center;
    padding:50px 30px;
    box-shadow:0 20px 50px rgba(0,0,0,.35);
    z-index:10;
}

.icon-box{
    width:120px;
    height:120px;
    margin:auto;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(255,255,255,.1);
    animation:spin 8s linear infinite;
}

.icon-box i{
    font-size:55px;
    color:#ffc107;
}

@keyframes spin{
    from{transform:rotate(0deg);}
    to{transform:rotate(360deg);}
}

h1{
    font-weight:700;
    margin-top:25px;
}

p{
    color:#cbd5e1;
    font-size:1.05rem;
}

.status{
    display:inline-block;
    padding:8px 18px;
    background:#ffc107;
    color:#000;
    border-radius:30px;
    font-weight:600;
    margin-top:10px;
}

.footer-text{
    margin-top:25px;
    font-size:.9rem;
    color:#94a3b8;
}
</style>
</head>
<body>

<div class="bg-circle circle1"></div>
<div class="bg-circle circle2"></div>

<div class="maintenance-card">

    <div class="icon-box">
        <i class="fas fa-gears"></i>
    </div>

    <span class="status">Maintenance Mode</span>

    <h1>We're Improving Our Website</h1>

    <p class="mt-3">
        Our website is currently undergoing scheduled maintenance.
        We are working hard to improve your experience and will be back shortly.
    </p>

    <div class="alert alert-warning mt-4 mb-0">
        <i class="fas fa-circle-info me-2"></i>
        Some services may be temporarily unavailable.
    </div>

    <div class="footer-text">
        © 2026 All Rights Reserved
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('contextmenu', function(e){
    e.preventDefault();
});
</script>

</body>
</html>