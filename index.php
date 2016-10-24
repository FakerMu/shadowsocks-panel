#!env php
<?php
/**
 * KK-Framework install
 * Author: kookxiang <r18@ikk.me>
 */

use Helper\Option;

if (php_sapi_name() != 'cli') {
    exit('Sorry, This page is not available due to incorrect server configuration.');
}

// Initialize constants
define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
define('LIBRARY_PATH', ROOT_PATH . 'Library/');
define('DATA_PATH', ROOT_PATH . 'Data/');
define('TIMESTAMP', time());
@ini_set('display_errors', 'on');
@ini_set('expose_php', false);
@date_default_timezone_set('Asia/Shanghai');
@ini_set('date.timezone', 'Asia/Shanghai');
set_time_limit(0);

// The system is
$runOS = (PHP_OS == 'WINNT') ? 1 : 0;

switch ($argv[1]) {
    case 'install':
        if (!file_exists(ROOT_PATH . 'composer.phar')) {
            if (download_composer("http://getcomposer.org/composer.phar") === false) {
                download_composer("http://static.loacg.com/soft/composer.phar");
            }
        }
        exec(PHP_BINARY . ' ' . ROOT_PATH . 'composer.phar -V', $return_arr);
        prints($return_arr);
        if(!file_exists(ROOT_PATH . 'composer.phar') || stripos($return_arr[count($return_arr)-1], 'Composer') === false) {
            @unlink(ROOT_PATH . 'composer.phar');
            echo colorize('Failed to download composer binary! Please try again', 'FAILURE') . PHP_EOL;
            break;
        }
        unset($return_arr);
        echo 'Now installing dependencies...' . PHP_EOL;
        if(!function_exists('system') || !function_exists('exec')) {
            echo colorize('FAILED! system() or exec() function is disabled!', 'FAILURE') . PHP_EOL;
            echo 'Run the command: ' . colorize('php -d disable_functions=\'\' index.php install', 'FAILURE') . PHP_EOL;
            break;
        }
        system(PHP_BINARY . ' ' . ROOT_PATH . 'composer.phar install');
        if (!file_exists(ROOT_PATH . 'Package/autoload.php')) {
            echo colorize('It seems composer failed to install package', 'FAILURE') . PHP_EOL;
            break;
        }
        echo 'Now reloading packages and config...'. PHP_EOL;
        $configFile = DATA_PATH . 'Config.php';
        if (!file_exists($configFile)) {
            echo 'Config Unknown... copying..' . PHP_EOL;
            copy(DATA_PATH . 'Config.simple.php', $configFile);
            echo colorize('Please modify ./Data/Config.php and try again', 'WARNING') . PHP_EOL;
            break;
        }

        @include ROOT_PATH . 'Package/autoload.php';
        try {
            @include DATA_PATH . 'Config.php';
        } catch (PDOException $e) {
            echo colorize('Database not available! Please modify the file ./Data/Config.php and try again', 'WARNING') . PHP_EOL;
            break;
        }
        if (Option::getConfig('ENCRYPT_KEY') == 'Please generate key and paste here') {
            Option::setConfig('ENCRYPT_KEY', Option::createKey());
        }
        echo 'Done!' . PHP_EOL;
        echo 'Now migrating database...' . PHP_EOL;
        if (PATH_SEPARATOR != ':') {
            $phinxCommand = ROOT_PATH . 'Package\bin\phinx.bat';
        } else {
            $phinxCommand = PHP_BINARY . ' ' . ROOT_PATH . 'Package/robmorgan/phinx/bin/phinx';
        }
        exec($phinxCommand . ' migrate', $return_arr, $return_arr2);
        prints($return_arr);
        if(stripos($return_arr[count($return_arr)-1], 'All Done.') === false) {
            echo colorize(PHP_EOL. PHP_EOL.'Failed to migrate database, you can try it manually: ', 'WARNING') . colorize('./Package/bin/phinx migrate', 'WARNING') . PHP_EOL;
            // rollback
            exec($phinxCommand . ' rollback', $return_arr, $return_arr2);
            break;
        }
        echo colorize('All done~ Cheers!', 'NOTE') . PHP_EOL;
        break;
    case 'import-sspanel':
        // TODO: 从 ss-panel 导入用户数据
        break;
    case 'test':
        echo colorize('FAILED!' . PHP_EOL, 'SUCCESS');
        echo colorize('FAILED!' . PHP_EOL, 'FAILURE');
        echo colorize('FAILED!' . PHP_EOL, 'WARNING');
        echo colorize('FAILED!' . PHP_EOL, 'NOTE');
        break;
    default:
        echo 'Unknown command';
}
echo PHP_EOL;



/**
 * Check command exists
 *
 * @param $command
 * @return bool
 */
function command_exists($command)
{
    $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';
    $process = proc_open(
        "$whereIsCommand $command",
        array(
            0 => array("pipe", "r"), //STDIN
            1 => array("pipe", "w"), //STDOUT
            2 => array("pipe", "w"), //STDERR
        ),
        $pipes
    );
    if ($process !== false) {
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return $stdout != '';
    }
    return false;
}

/**
 * Unix/Linux terminal echo color support
 *
 * @param $text
 * @param $status
 * @return string
 * @throws Exception
 */
function colorize($text, $status) {
    $out = "";
    switch($status) {
        case "SUCCESS":
            $out = "[44m"; //Blue fonts
            break;
        case "FAILURE":
            $out = "[1;137;41m"; //Red background
            break;
        case "WARNING":
            $out = "[1;37;31m"; //Red fonts
            break;
        case "NOTE":
            $out = "[1;134;34m"; //Blue background
            break;
        default:
            throw new Exception("Invalid status: " . $status);
    }
    return chr(27) . "$out" . "$text" . chr(27) . "[0m";
}

/**
 * download composer and try
 *
 * @param $url
 * @return bool
 */
function download_composer($url) {
    echo 'Downloading composer...';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $binary = curl_exec($ch);
    if (curl_errno($ch)) {
        echo colorize('FAILED!' . PHP_EOL . curl_error($ch), 'FAILURE');
        curl_close($ch);
        return false;
    }
    $fp = fopen(ROOT_PATH . 'composer.phar', 'wb');
    fputs($fp, $binary);
    fclose($fp);
    curl_close($ch);
    echo 'Done!' . PHP_EOL;
}

/**
 * Print array
 *
 * @param array $array
 */
function prints(array $array) {
    foreach($array as $v) {
        echo $v . PHP_EOL;
    }
}
