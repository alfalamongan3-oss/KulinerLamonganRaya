<?php
// Matikan pesan error HTML bawaan PHP agar tidak merusak format JSON
error_reporting(0); 
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// --- KONFIGURASI DATABASE (WAJIB DIGANTI) ---
$host = "localhost"; 
$user = "u759560335_akmal";   // <-- CEK LAGI DI HOSTINGER
$pass = "Alfa2008!?"; // <-- CEK LAGI PASSWORDNYA
$db   = "u759560335_kulinerAkmalDB"; // <-- CEK LAGI NAMA DATABASENYA

// Coba koneksi dengan Try-Catch agar error bisa ditangkap rapi
try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Koneksi Database Gagal: " . $conn->connect_error);
    }
} catch (Exception $e) {
    // Jika gagal, kirim pesan error dalam format JSON yang bisa dibaca index.html
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// --- FUNGSI UPLOAD ---
function uploadImage($file) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $target_file = $target_dir . time() . "_" . rand(1000,9999) . "." . $ext;
    
    if(!in_array($ext, ['jpg','jpeg','png','webp'])) return ["status"=>false, "msg"=>"Format foto harus JPG/PNG"];
    if($file["size"] > 2000000) return ["status"=>false, "msg"=>"Foto terlalu besar (Max 2MB)"];
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) return ["status"=>true, "path"=>$target_file];
    return ["status"=>false, "msg"=>"Gagal upload ke server. Cek permission folder uploads."];
}

// --- ENDPOINTS ---

// 1. REGISTER
if ($action == 'register') {
    $input = file_get_contents("php://input");
    $data = json_decode($input);
    
    if (!$data) { echo json_encode(["status"=>"error", "message"=>"Data kosong/format salah"]); exit; }

    $u = $conn->real_escape_string($data->username);
    $p = $conn->real_escape_string($data->password);
    $wa = $conn->real_escape_string($data->whatsapp);
    $adr = $conn->real_escape_string($data->address);

    $check = $conn->query("SELECT id FROM users WHERE username='$u'");
    if ($check && $check->num_rows > 0) { echo json_encode(["status"=>"error", "message"=>"Username sudah dipakai"]); }
    else {
        $sql = "INSERT INTO users (username, password, whatsapp_number, address) VALUES ('$u', '$p', '$wa', '$adr')";
        if ($conn->query($sql)) echo json_encode(["status"=>"success", "message"=>"Daftar Berhasil"]);
        else echo json_encode(["status"=>"error", "message"=>"Gagal DB: ".$conn->error]);
    }
}
// 2. LOGIN
elseif ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"));
    $u = $conn->real_escape_string($data->username);
    $p = $conn->real_escape_string($data->password);
    $res = $conn->query("SELECT * FROM users WHERE username='$u' AND password='$p'");
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo json_encode(["status"=>"success", "username"=>$u]);
    } else echo json_encode(["status"=>"error", "message"=>"Login Gagal. Cek Username/Password."]);
}
// 3. ADD FOOD
elseif ($action == 'add_food') {
    if (!isset($_POST['name'])) { echo json_encode(["status"=>"error", "message"=>"Data tidak lengkap"]); exit; }

    $name = $conn->real_escape_string($_POST['name']);
    $cat = $conn->real_escape_string($_POST['category']);
    $desc = $conn->real_escape_string($_POST['desc']);
    $seller = $conn->real_escape_string($_POST['seller']);
    
    if(isset($_FILES['img_file'])) {
        $up = uploadImage($_FILES['img_file']);
        if(!$up['status']) die(json_encode(["status"=>"error", "message"=>$up['msg']]));
        $img = $up['path'];
    } else die(json_encode(["status"=>"error", "message"=>"Wajib Upload Foto"]));

    if($conn->query("INSERT INTO foods (name,category,description,img,seller) VALUES ('$name','$cat','$desc','$img','$seller')"))
        echo json_encode(["status"=>"success", "message"=>"Menu Terbit"]);
    else echo json_encode(["status"=>"error", "message"=>"Err: ".$conn->error]);
}
// 4. EDIT FOOD
elseif ($action == 'edit_food') {
    $id = $conn->real_escape_string($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $cat = $conn->real_escape_string($_POST['category']);
    $desc = $conn->real_escape_string($_POST['desc']);
    $seller = $conn->real_escape_string($_POST['seller']);
    
    $img_sql = "";
    if(isset($_FILES['img_file']) && $_FILES['img_file']['error']===0) {
        $up = uploadImage($_FILES['img_file']);
        if(!$up['status']) die(json_encode(["status"=>"error", "message"=>$up['msg']]));
        $img_sql = ", img='".$up['path']."'";
    }
    if($conn->query("UPDATE foods SET name='$name', category='$cat', description='$desc' $img_sql WHERE id='$id' AND seller='$seller'"))
        echo json_encode(["status"=>"success", "message"=>"Menu Updated"]);
    else echo json_encode(["status"=>"error", "message"=>"Err: ".$conn->error]);
}
// 5. DELETE
elseif ($action == 'delete_food') {
    $d = json_decode(file_get_contents("php://input"));
    $id = $conn->real_escape_string($d->id);
    $seller = $conn->real_escape_string($d->seller);
    if($conn->query("DELETE FROM foods WHERE id='$id' AND seller='$seller'")) 
        echo json_encode(["status"=>"success", "message"=>"Menu Dihapus"]);
    else echo json_encode(["status"=>"error", "message"=>"Gagal Hapus"]);
}
// 6. GET
elseif ($action == 'get_foods') {
    $res = $conn->query("SELECT foods.*, users.whatsapp_number, users.address FROM foods JOIN users ON foods.seller = users.username ORDER BY foods.id DESC");
    $out = [];
    if ($res) {
        while($r = $res->fetch_assoc()) $out[] = $r;
    }
    echo json_encode($out);
}
else {
    // Jika dibuka langsung tanpa action, tampilkan pesan sukses (untuk tes koneksi)
    echo json_encode(["status"=>"info", "message"=>"API Berjalan. Koneksi Database Sukses!"]);
}
$conn->close();
?>