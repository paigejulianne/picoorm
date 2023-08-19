<?php
error_reporting(E_ALL);

$inc_start = microtime_float();
require_once "src/autoload.inc.php";
$inc_end = microtime_float(); 


$code = $_POST['code'] ?? '';


function microtime_float() {
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}


function truncate($str, $len, $suffix = '...') {
	return (strlen($str) <= $len) ? $str : substr($str, 1, $len).$suffix;
}

// Initialize exec history
if (empty($_SESSION['exec']['history'])) { $_SESSION['exec']['history'] = array(); }

// Save code exec history
if (!empty($_POST['code']) and !in_array($_POST['code'], $_SESSION['exec']['history'])) { array_unshift($_SESSION['exec']['history'], $_POST['code']); }
while (count($_SESSION['exec']['history']) > 20) { array_pop($_SESSION['exec']['history']); }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
	<head>
	<title>PHP Test Fixture</title>
	</head>
	<body style="font-family: Tahoma, sans-serif; font-size: 13px;">
		<form name="exec" id="exec" method="post" action="">
			Code to Execute:<br />
			<textarea id="code" name="code" style="width: 100%;" rows="10">
<?php if(empty($code)): ?>$GLOBALS['_PICO_PDO'] = new PDO("mysql:host=localhost;dbname=picoorm;","picoorm","picoorm");<?php endif; ?>
                <?php echo $code ?>
            </textarea>
			<br />

			<div id="hist0"></div>
<?php
// Display exec history
foreach ($_SESSION['exec']['history'] as $idx => $history) {
	echo "\t\t\t", '<div id="hist', $idx + 1, '" style="display: none;">', htmlspecialchars($history), '</div>', "\n";
}
?>
			History 
			<select style="font-family: 'Courier New', courier, monospace; font-size: 13px;" onChange="document.getElementById('code').innerHTML = document.getElementById('hist' + this.value).innerHTML; document.exec.code.focus();">
				<option value="0">&nbsp;</option>
<?php
foreach ($_SESSION['exec']['history'] as $idx => $history) {
	echo "\t\t\t\t", '<option value="', $idx + 1, '">', htmlspecialchars(truncate(str_replace("\r", ' ', str_replace("\n", ' ', str_replace("\r\n", ' ', $history))), 120)), '</option>', "\n";
}
?>
			</select>
			<br />
			<br />
			<input type="submit" name="Submit" value="Execute (Alt-S)" accesskey="s" />
			<hr />
			<h3>Result:</h3>
			<pre><?php $eval_time_start = microtime_float(); echo eval($code); $eval_time_stop = microtime_float(); ?></pre>
			<br/><hr/>
			<h3>Profiling information:</h3>
			<table cellspacing="4" cellpadding="0" border="0" style="font-family: Tahoma, sans-serif; font-size: 13px; vertical-align: top">
			<tr><td align="right"><b>Script evaluation time:</b></td><td><?php echo number_format($eval_time_stop - $eval_time_start, 5); ?>secs</td></tr>
			<tr><td align="right"><b>Include evaluation time:</b></td><td><?php echo number_format($inc_end - $inc_start, 5); ?>secs</td></tr>
			<tr><td align="right"><b>Total evaluation time:</b></td><td><?php echo number_format(microtime_float() - $inc_start, 5); ?>sec<sup>&dagger;</sup></td></tr>
			</table>
		</form>
		<font size="1"><sup>&dagger;</sup>Includes other render time not accounted for by either 'script' or 'include' evaluation time</font>
	</body>
</html>
