<?php
// Smart Student Management - single front controller (works on Vercel + XAMPP)
function db(){
 static $p;
 if($p instanceof PDO)return $p;
 $host=getenv('DB_HOST')?:'localhost';
 $port=getenv('DB_PORT')?:3306;
 $name=getenv('DB_NAME')?:'smart_sms';
 $user=getenv('DB_USER')?:'root';
 $pass=getenv('DB_PASS')?:'';
 $o=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
 if(getenv('DB_SSL')){$o[PDO::MYSQL_ATTR_SSL_CA]='/etc/ssl/certs/ca-certificates.crt';$o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=false;}
 try{
  $p=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,$o);
 }catch(PDOException $e){
  if(getenv('DB_AUTO_CREATE')==='0')throw $e;
  $root=new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$user,$pass,$o);
  $root->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`','',$name)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $p=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,$o);
  $schema=__DIR__.'/../schema.sql';
  if(is_file($schema)){
   $sql=file_get_contents($schema);
   $sql=preg_replace('/--.*$/m','',$sql);
   foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $stmt){
    try{$p->exec($stmt);}catch(PDOException $ignore){}
   }
  }
 }
 // Upgrade older Smart SMS databases automatically.
 // Older installations may not have the new class-based columns.
 try{$p->exec("ALTER TABLE users ADD COLUMN class_name ENUM('5','6','7','8','9','10') NULL");}catch(PDOException $ignore){}
 try{$p->exec("ALTER TABLE notes ADD COLUMN class_name ENUM('5','6','7','8','9','10') NULL");}catch(PDOException $ignore){}
 // Login tokens make every browser tab independent and allow logout to invalidate only that tab.
 try{$p->exec("CREATE TABLE IF NOT EXISTS login_tokens (token_hash CHAR(64) PRIMARY KEY,user_id INT NOT NULL,expires_at DATETIME NOT NULL,INDEX(user_id),INDEX(expires_at))");}catch(PDOException $ignore){}
 try{$p->exec("DELETE FROM login_tokens WHERE expires_at < NOW()");}catch(PDOException $ignore){}
 // Keep the default admin available on a fresh or upgraded database.
 try{$p->exec("INSERT IGNORE INTO users(name,email,pass,role) VALUES ('Admin','admin@school.com','\$2y\$10\$b9aDh8e2gPyE0rLaXGz6v.mJSDK1ltg1hQuVVWB6wmscnC/xawzkS','admin')");}catch(PDOException $ignore){}
 return $p;
}
function q($s,$a=[]){$t=db()->prepare($s);$t->execute($a);return $t;}
function rows($s,$a=[]){return q($s,$a)->fetchAll();}
function one($s,$a=[]){return q($s,$a)->fetch();}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function secret(){return getenv('APP_SECRET')?:'change-me-in-production';}
function tab_token($id){$d=['u'=>(int)$id,'e'=>time()+604800,'n'=>bin2hex(random_bytes(16))];$t=rtrim(strtr(base64_encode(json_encode($d)),'+/','-_'),'=');return $t.'.'.hash_hmac('sha256',$t,secret());}
function current_tab(){global $TAB_TOKEN;if(!empty($_GET['tab']))return preg_replace('/[^A-Za-z0-9_.-]/','',$_GET['tab']);return $TAB_TOKEN??'';}
function login_user($id){global $TAB_TOKEN;$TAB_TOKEN=tab_token($id);try{q('INSERT INTO login_tokens(token_hash,user_id,expires_at) VALUES(?,?,FROM_UNIXTIME(?))',[hash('sha256',$TAB_TOKEN),(int)$id,time()+604800]);}catch(Throwable $x){}}
function me(){static $m=false;if($m!==false)return $m;$m=null;$c=current_tab();[$t,$s]=array_pad(explode('.',$c,2),2,'');if($t&&$s&&hash_equals(hash_hmac('sha256',$t,secret()),$s)){$raw=strtr($t,'-_','+/');$raw.=str_repeat('=',(4-strlen($raw)%4)%4);$d=json_decode(base64_decode($raw),true);if($d&&($d['e']??0)>time()){try{$ok=one('SELECT token_hash FROM login_tokens WHERE token_hash=? AND user_id=? AND expires_at>NOW()',[hash('sha256',$c),$d['u']]);if($ok)$m=one('SELECT * FROM users WHERE id=?',[$d['u']])?:null;}catch(Throwable $x){$m=one('SELECT * FROM users WHERE id=?',[$d['u']])?:null;}}}return $m;}
function base_url(){static $b=null;if($b!==null)return $b;$script=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??''));$b=preg_replace('#/api$#','',$script);if($b==='/'||$b==='.')$b='';return rtrim($b,'/');}
function url($p='/'){$p='/'.ltrim($p,'/');$tab=current_tab();if($tab && !str_contains($p,'tab=')){$p.=str_contains($p,'?')?'&':'?';$p.='tab='.rawurlencode($tab);}return base_url().$p;}
function tab_field(){ $t=current_tab(); return $t?'<input type="hidden" name="tab" value="'.e($t).'">':'';}
function go($p){header('Location: '.url($p));exit;}
function logout_go(){global $TAB_TOKEN;$c=current_tab();if($c){try{q('DELETE FROM login_tokens WHERE token_hash=?',[hash('sha256',$c)]);}catch(Throwable $x){}}$TAB_TOKEN='';header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');header('Pragma: no-cache');header('Location: '.base_url().'/login');exit;}
function post($k){return trim($_POST[$k]??'');}
function flash($m=null){if($m!==null){setcookie('flash',$m,time()+30,'/');return;}$f=$_COOKIE['flash']??'';if($f)setcookie('flash','',time()-10,'/');return $f;}

function head($title,$body=''){echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · Smart SMS</title><link rel="stylesheet" href="'.url('/assets/style.css').'"></head><body class="'.$body.'">';}
function menu($r){
 $m=['dashboard'=>['🏠','Dashboard']];
 if($r=='student')$m+=['attendance'=>['📅','Attendance'],'fees'=>['💳','Fees'],'marks'=>['🏆','Marks']];
 if($r=='faculty')$m+=['mark-attendance'=>['✅','Mark Attendance'],'marks'=>['🏆','Enter Marks']];
 if($r=='admin')$m+=['users'=>['👥','Users'],'classes'=>['🏫','Classes'],'fees'=>['💳','Fees'],'fee-status'=>['📊','Fee Status']];
 return $m+['calendar'=>['🗓️','Calendar'],'notes'=>['📝','Notes']];
}
function layout($page,$title,$fn){$u=me();head($title);echo '<aside class="side"><div class="brand">🎓 Smart<span>SMS</span></div>';
 foreach(menu($u['role']) as $k=>[$i,$l])echo '<a href="'.url('/'.$k).'" class="'.($page==$k?'on':'').'">'.$i.' '.$l.'</a>';
 echo '<a href="'.url('/logout').'" class="out">🚪 Logout</a></aside><main><header><div><h1>'.e($title).'</h1><p>Welcome, '.e($u['name']).' <span class="pill">'.e($u['role']).'</span></p></div><button id="th" title="Theme">🌗</button></header>';
 if($f=flash())echo '<div class="alert">'.e($f).'</div>';
 $fn($u);echo '</main><script src="'.url('/assets/app.js').'"></script></body></html>';}
function table($h,$r){echo '<div class="card"><div class="tw"><table><tr>';foreach($h as $x)echo '<th>'.$x.'</th>';echo '</tr>';
 foreach($r as $row){echo '<tr>';foreach($row as $c)echo '<td>'.$c.'</td>';echo '</tr>';}if(!$r)echo '<tr><td colspan="'.count($h).'" class="muted">No data yet</td></tr>';echo '</table></div></div>';}
function students($class=null){return $class!==null && $class!=='' ? rows("SELECT id,name,roll_no,class_name FROM users WHERE role='student' AND class_name=? ORDER BY roll_no",[$class]) : rows("SELECT id,name,roll_no,class_name FROM users WHERE role='student' ORDER BY class_name,roll_no");}
function class_opts($selected=''){ $o=''; for($i=5;$i<=10;$i++){ $c=(string)$i; $o.='<option value="'.$c.'" '.($selected===$c?'selected':'').'>Class '.$c.'</option>'; } return $o; }
function opts($class=null){$o='';foreach(students($class) as $s)$o.='<option value="'.$s['id'].'">'.e($s['roll_no'].' - '.$s['name']).'</option>';return $o;}
function show_stat($i,$l,$v){echo '<div class="stat"><div class="ic">'.$i.'</div><div><b>'.$v.'</b><small>'.$l.'</small></div></div>';}

$requestPath=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/'; $base=base_url(); if($base!=='' && str_starts_with($requestPath,$base))$requestPath=substr($requestPath,strlen($base)); $path=trim($requestPath,'/'); $path=$path==''||$path=='api/index.php'?'dashboard':$path;
try{
if($path=='logout'){logout_go();}
if($path=='login'||$path=='register'){
 if(me())go('/dashboard');$err='';
 if($_SERVER['REQUEST_METHOD']=='POST'){
  if($path=='login'){$role=post('role');$class=post('class_name');$u=one('SELECT * FROM users WHERE email=?',[post('email')]);
   if($u&&$u['role']==$role&&password_verify($_POST['pass']??'',$u['pass'])&&($role!='student'||$u['class_name']==$class)){login_user($u['id']);go('/dashboard');}$err='Invalid login details, role, or class';}
  else{$n=post('name');$em=strtolower(post('email'));$ro=post('roll_no');$class=post('class_name');$regRole=post('role');$pw=$_POST['pass']??'';
   if(!$n||!filter_var($em,FILTER_VALIDATE_EMAIL)||!in_array($regRole,['student','faculty','admin'])||strlen($pw)<6)$err='Fill all required fields';
   elseif($regRole==='student' && (!$ro||!in_array($class,['5','6','7','8','9','10'])))$err='Student must select a roll number and Class 5 to 10';
   elseif(one('SELECT id FROM users WHERE email=?',[$em]) || ($ro && one('SELECT id FROM users WHERE roll_no=?',[$ro])))$err='Email or roll number already registered';
   else{if($regRole==='student')q('INSERT INTO users(name,email,pass,roll_no,class_name,role) VALUES(?,?,?,?,?,?)',[$n,$em,password_hash($pw,PASSWORD_BCRYPT),$ro,$class,$regRole]);else q('INSERT INTO users(name,email,pass,role) VALUES(?,?,?,?)',[$n,$em,password_hash($pw,PASSWORD_BCRYPT),$regRole]);login_user(db()->lastInsertId());go('/dashboard');}}}
 head(ucfirst($path),'auth');echo '<div class="blob b1"></div><div class="blob b2"></div><form method="post" class="authbox"><div class="brand big">🎓 Smart<span>SMS</span></div><h2>'.($path=='login'?'Welcome back':'Create account').'</h2>';
 if($err)echo '<div class="alert err">'.e($err).'</div>';
 if($path=='register')echo '<input name="name" placeholder="Full name" required>';
 echo '<select name="role" id="loginRole" required><option value="student">Student</option><option value="faculty">Faculty</option><option value="admin">Admin</option></select>'.($path=='register'?'<div id="studentRegisterFields"><input name="roll_no" id="registerRoll" placeholder="Roll number"><select name="class_name" id="registerClass"><option value="">Select class (students)</option>'.class_opts().'</select></div>':'<div id="studentClassWrap"><select name="class_name" id="studentClass"><option value="">Select class (students)</option>'.class_opts().'</select></div>').'<input type="email" name="email" placeholder="Email" required><input type="password" name="pass" placeholder="Password" required><button class="btn">'.($path=='login'?'Login':'Register').'</button><p class="muted">'.($path=='login'?'New student? <a href="'.url('/register').'">Register</a>':'Have an account? <a href="'.url('/login').'">Login</a>').'</p></form>'.($path=='login'?'<script>const r=document.getElementById("loginRole"),w=document.getElementById("studentClassWrap"),c=document.getElementById("studentClass");function toggleClass(){const student=r.value==="student";w.style.display=student?"block":"none";c.disabled=!student;c.required=student;if(!student)c.value="";}r.addEventListener("change",toggleClass);toggleClass();</script>':'<script>const r=document.getElementById("loginRole"),w=document.getElementById("studentRegisterFields"),roll=document.getElementById("registerRoll"),cls=document.getElementById("registerClass");function toggleRegister(){const student=r.value==="student";w.style.display=student?"block":"none";roll.disabled=!student;cls.disabled=!student;roll.required=student;cls.required=student;if(!student){roll.value="";cls.value="";}}r.addEventListener("change",toggleRegister);toggleRegister();</script>').'</body></html>';exit;}

$u=me();if(!$u)go('/login');$r=$u['role'];$M=$_SERVER['REQUEST_METHOD']=='POST';
if(!isset(menu($r)[$path])){http_response_code(403);go('/dashboard');}
switch($path){
case 'dashboard': layout($path,'Dashboard',function($u){$r=$u['role'];echo '<div class="grid">';
 if($r=='student'){$a=one("SELECT COUNT(*) t,SUM(status='P') p FROM attendance WHERE student_id=?",[$u['id']]);$pc=$a['t']?round($a['p']/$a['t']*100):0;
  $f=one('SELECT COALESCE(SUM(amount),0) s FROM fees WHERE student_id=? AND paid=0',[$u['id']]);$m=one('SELECT AVG(score/max_score*100) a FROM marks WHERE student_id=?',[$u['id']]);
  show_stat('📅','Attendance',$pc.'%');show_stat('💳','Fees due','₹'.number_format($f['s']));show_stat('🏆','Avg marks',round($m['a']??0).'%');show_stat('🎫','Roll no',e($u['roll_no']));show_stat('🏫','Class','Class '.e($u['class_name']));}
 else{foreach(['student'=>['🎓','Students'],'faculty'=>['👩‍🏫','Faculty']] as $k=>[$i,$l])show_stat($i,$l,one('SELECT COUNT(*) c FROM users WHERE role=?',[$k])['c']);
  show_stat('🗓️','Events',one('SELECT COUNT(*) c FROM events')['c']);show_stat('📝','Notes',one('SELECT COUNT(*) c FROM notes')['c']);}
 echo '</div><h3>Upcoming events</h3>';table(['Date','Event','Info'],array_map(fn($x)=>[e($x['date']),e($x['title']),e($x['info'])],rows('SELECT * FROM events WHERE date>=CURDATE() ORDER BY date LIMIT 5')));});break;

case 'attendance': layout($path,'My Attendance',function($u){table(['Date','Status'],array_map(fn($x)=>[e($x['date']),$x['status']=='P'?'<span class="tag g">Present</span>':'<span class="tag r">Absent</span>'],rows('SELECT * FROM attendance WHERE student_id=? ORDER BY date DESC',[$u['id']])));});break;

case 'mark-attendance':
 if($M){$d=post('date');$class=post('class_name');foreach(students($class) as $st){$id=$st['id'];$s=$_POST['st'][$id]??'A';q('REPLACE INTO attendance(student_id,date,status) VALUES(?,?,?)',[$id,$d,$s=='P'?'P':'A']);}flash('Attendance saved for Class '.$class.' on '.$d);go('/mark-attendance?class='.urlencode($class));}
 layout($path,'Mark Attendance',function($u){$class=$_GET['class']??'5';echo '<form method="post" action="'.url('/mark-attendance').'" class="card">'.tab_field().'<div class="row"><select name="class_name" required>'.class_opts($class).'</select><label>Date <input type="date" name="date" value="'.date('Y-m-d').'" required></label><button class="btn">Load Class</button></div><div class="tw"><table><tr><th>Roll</th><th>Name</th><th>Present</th><th>Absent</th></tr>';
  foreach(students($class) as $s)echo '<tr><td>'.e($s['roll_no']).'</td><td>'.e($s['name']).'</td><td><input type="radio" name="st['.$s['id'].']" value="P" checked></td><td><input type="radio" name="st['.$s['id'].']" value="A"></td></tr>';
  echo '</table></div><button class="btn">Save attendance</button></form>';});break;

case 'fees':
 if($M&&$r=='admin'){if(isset($_POST['toggle']))q('UPDATE fees SET paid=1-paid WHERE id=?',[(int)$_POST['toggle']]);else { $class=post('class_name'); $title=post('title'); $amount=(float)$_POST['amount']; $due=$_POST['due']; foreach(students($class) as $st) q('INSERT INTO fees(student_id,title,amount,due_date) VALUES(?,?,?,?)',[$st['id'],$title,$amount,$due]); } flash('Fees updated for the selected class');go('/fees');}
 layout($path,$r=='admin'?'Class Fees':'My Fees',function($u){$a=$u['role']=='admin';
  if($a){echo '<form method="post" action="'.url('/fees').'" class="card row">'.tab_field().'<select name="class_name" required>'.class_opts().'</select><input name="title" placeholder="Fee title (e.g. Tuition)" required><input type="number" step="0.01" name="amount" placeholder="Amount per student" required><input type="date" name="due" required><button class="btn">Set Fee For Whole Class</button></form><p class="muted">Set one fee for every student in the selected class. Use Fee Status for class-wise paid/due information.</p>';
   $summary=[];for($i=5;$i<=10;$i++){ $c=(string)$i;$x=one("SELECT COUNT(DISTINCT u.id) students,COALESCE(SUM(f.amount),0) total,COALESCE(SUM(CASE WHEN f.paid=1 THEN f.amount ELSE 0 END),0) paid FROM users u LEFT JOIN fees f ON f.student_id=u.id WHERE u.role='student' AND u.class_name=?",[$c]);$summary[]=["Class $c",$x['students'],'₹'.number_format($x['total'],2),'₹'.number_format($x['paid'],2),'₹'.number_format($x['total']-$x['paid'],2),'<a class="mini" href="'.url('/fee-status?class='.$c).'">View Status</a>'];} table(['Class','Students','Total Fees','Paid','Due',''], $summary);
  }else{$l=rows('SELECT f.* FROM fees f WHERE student_id=? ORDER BY due_date DESC',[$u['id']]);table(['Fee','Amount','Due','Status'],array_map(fn($x)=>[e($x['title']),'₹'.number_format($x['amount']),e($x['due_date']),$x['paid']?'<span class="tag g">Paid</span>':'<span class="tag r">Due</span>'],$l));}});break;

case 'fee-status':
 if($r!='admin')go('/dashboard');
 layout($path,'Class Fee Status',function($u){$class=$_GET['class']??'5';echo '<form method="get" action="'.url('/fee-status').'" class="card row"><select name="class">'.class_opts($class).'</select><button class="btn">View Class</button></form>'; $list=rows("SELECT u.id,u.name,u.email,u.roll_no,u.class_name,COALESCE(SUM(f.amount),0) total,COALESCE(SUM(CASE WHEN f.paid=1 THEN f.amount ELSE 0 END),0) paid FROM users u LEFT JOIN fees f ON f.student_id=u.id WHERE u.role='student' AND u.class_name=? GROUP BY u.id ORDER BY u.roll_no",[$class]);table(['Roll No','Student','Email','Class','Total','Paid','Status'],array_map(fn($x)=>[e($x['roll_no']),e($x['name']),e($x['email']),e($x['class_name']),'₹'.number_format($x['total'],2),'₹'.number_format($x['paid'],2),((float)$x['total']-(float)$x['paid'])<=0?'<span class="tag g">Paid</span>':'<span class="tag r">Not Paid / Due</span>'],$list));});break;

case 'marks':
 if($M&&$r=='faculty'){q('INSERT INTO marks(student_id,subject,score,max_score) VALUES(?,?,?,?)',[$_POST['sid'],post('subject'),$_POST['score'],$_POST['max']?:100]);flash('Marks added');go('/marks?class='.urlencode(post('class_name')));}
 layout($path,$r=='faculty'?'Enter Marks':'My Marks',function($u){$f=$u['role']=='faculty';$class=$_GET['class']??'5';
  if($f)echo '<form method="post" class="card row"><select name="class_name" onchange="location.href=\''.url('/marks').(str_contains(url('/marks'),'?')?'&':'?').'class=\'+this.value"><option value="">Select class</option>'.class_opts($class).'</select><select name="sid">'.opts($class).'</select><input name="subject" placeholder="Subject" required><input type="number" step="0.01" name="score" placeholder="Score" required><input type="number" name="max" placeholder="Out of (100)"><button class="btn">Add</button></form>';
  $l=$f?rows('SELECT m.*,u.name FROM marks m JOIN users u ON u.id=m.student_id ORDER BY m.id DESC LIMIT 50'):rows('SELECT *,"" name FROM marks WHERE student_id=?',[$u['id']]);
  table(array_merge($f?['Student']:[],['Subject','Score','%']),array_map(fn($x)=>array_merge($f?[e($x['name'])]:[],[e($x['subject']),e($x['score']).' / '.e($x['max_score']),'<div class="bar"><i style="width:'.round($x['score']/$x['max_score']*100).'%"></i></div>']),$l));});break;

case 'classes':
 if($r!='admin')go('/dashboard');
 layout($path,'Class Students',function($u){$class=$_GET['class']??'5'; echo '<form method="get" action="'.url('/classes').'" class="card row"><select name="class">'.class_opts($class).'</select><button class="btn">View Class</button></form>'; $list=rows("SELECT id,name,email,roll_no,class_name FROM users WHERE role='student' AND class_name=? ORDER BY roll_no",[$class]); table(['Roll No','Student','Email','Class'],array_map(fn($x)=>[e($x['roll_no']),e($x['name']),e($x['email']),e($x['class_name'])],$list));});break;

case 'calendar':
 if($M&&$r!='student'){q('INSERT INTO events(title,date,info) VALUES(?,?,?)',[post('title'),$_POST['date'],post('info')]);flash('Event added');go('/calendar');}
 layout($path,'Academic Calendar',function($u){if($u['role']!='student')echo '<form method="post" class="card row"><input name="title" placeholder="Event title" required><input type="date" name="date" required><input name="info" placeholder="Details"><button class="btn">Add event</button></form>';
  table(['Date','Event','Details'],array_map(fn($x)=>[e($x['date']),e($x['title']),e($x['info'])],rows('SELECT * FROM events ORDER BY date')));});break;

case 'notes':
 if($M&&$r!='student'){q('INSERT INTO notes(title,body,author,class_name) VALUES(?,?,?,?)',[post('title'),post('body'),$u['name'],post('class_name')]);flash('Note published for Class '.post('class_name'));go('/notes?class='.urlencode(post('class_name')));}
 layout($path,'Notes & Announcements',function($u){$class=$_GET['class']??($u['role']=='student'?$u['class_name']:'5');if($u['role']!='student')echo '<form method="post" action="'.url('/notes').'" class="card">'.tab_field().'<div class="row"><select name="class_name" required>'.class_opts($class).'</select><input name="title" placeholder="Title" required></div><textarea name="body" placeholder="Write notes for this class..." required></textarea><button class="btn">Publish Notes</button></form>';
  $list=$u['role']=='student'?rows('SELECT * FROM notes WHERE class_name=? ORDER BY id DESC',[$u['class_name']]):rows('SELECT * FROM notes WHERE class_name=? ORDER BY id DESC',[$class]);foreach($list as $n)echo '<div class="card"><span class="tag g">Class '.e($n['class_name']).'</span><h3>'.e($n['title']).'</h3><p>'.nl2br(e($n['body'])).'</p><small class="muted">By '.e($n['author']).' · '.e($n['created_at']).'</small></div>';});break;

case 'users':
 if($M){$id=(int)$_POST['id'];if(isset($_POST['del'])&&$id!=$u['id'])q('DELETE FROM users WHERE id=?',[$id]);elseif(isset($_POST['role'])&&in_array($_POST['role'],['student','faculty','admin']))q('UPDATE users SET role=? WHERE id=?',[$_POST['role'],$id]);flash('User updated');go('/users');}
 layout($path,'Manage Users',function($u){table(['Name','Email','Roll','Class','Role','Action'],array_map(function($x){$s='<form method="post" class="row"><input type="hidden" name="id" value="'.$x['id'].'"><select name="role" onchange="this.form.submit()">';
  foreach(['student','faculty','admin'] as $o)$s.='<option '.($o==$x['role']?'selected':'').'>'.$o.'</option>';return [e($x['name']),e($x['email']),e($x['roll_no']),e($x['class_name']),$s.'</select><button class="mini red" name="del" onclick="return confirm(\'Delete user?\')">Delete</button></form>',''];},rows('SELECT * FROM users ORDER BY id')));});break;
}
}catch(Throwable $x){http_response_code(500);head('Error');echo '<div class="authbox"><h2>Something went wrong</h2><p class="muted">'.e((getenv('DEBUG') || ($_SERVER['SERVER_NAME']??'')==='localhost')?$x->getMessage():'Check database settings (see README).').'</p></div>';}
