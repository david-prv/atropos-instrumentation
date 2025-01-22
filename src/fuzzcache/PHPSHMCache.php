<?php
/**
 * PHPSHMCache.php
 *
 * @author Penghui Li <lipenghui315@gmail.com>
 * @author David Dewes <dade00003@stud.uni-saarland.de>
 */

namespace PHPSHMCache;

error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * from openemr: https://github.com/openemr/openemr/portal/patient/fwk/libs/verysimple/DB/DataDriver/MySQLi.php
 */

/** @var array $BAD_CHARS characters that will be escaped */
static $BAD_CHARS = array(
    "\\",
    "\0",
    "\n",
    "\r",
    "\x1a",
    "'",
    '"'
);

/** @var array $GOOD_CHARS characters that will be used to replace bad chars */
static $GOOD_CHARS = array(
    "\\\\",
    "\\0",
    "\\n",
    "\\r",
    "\Z",
    "\'",
    '\"'
);

/**
 * Get a System V IPC Key
 *
 * @param string $path Path to an accessible file.
 * @param string $pj Project identifier.
 * @return int The key.
 */
function getSHMKey(string $path, string $pj = "b"): int
{
    return ftok($path, $pj);
}

/**
 * Get the microsecond from microtime() and offset $microseconds
 *
 * @param int $microseconds The offset in microseconds.
 * @return int The microseconds + offset.
 */
function microtime(int $microseconds = 0): int
{
    return intval(round(microtime(true) * 1000)) + $microseconds;
}


/**
 * Package to an array and serialize to a string
 *
 * @param mixed $data
 * @param int $seconds [optional]
 * @return string
 */
function pack(mixed $data, int $seconds = 0): string
{
    return serialize(
        array(
            'data' => $data,
            'timeout' => $seconds ? microtime($seconds * 1000) : 0,
        )
    );
}


/**
 * Unpacking a string and parse no timeout data from array
 *
 * @param string $data
 * @return mixed|bool
 */
function unpack(string $data): mixed
{
    return unserialize($data);
}

/**
 * Clean data from shared memory block
 *
 * @param int $shmKey The shared memory key.
 * @return bool Indicator if operation was successful.
 */
function clean(int $shmKey): bool
{
    // Check if the shared memory segment with the given shm key exists
    $id = @shmop_open($shmKey, 'a', 0, 0);

    if ($id !== false) {
        // The shared memory segment exists, so delete the old data
        print("[clean] remove $shmKey\n");
        shmop_delete($id);
        return true;
    }
    return false;
}

/**
 * Write data into shared memory block
 *
 * @param mixed $data
 * @param int $seconds [optional]
 * @return bool
 */
function write($shmKey, $data, int $seconds = 0): bool
{
    clean($shmKey);

    $data = pack($data, $seconds);
    $id = shmop_open($shmKey, "n", 0644, strlen($data));
    if ($id === false) {
        return false;
    }

    $size = shmop_write($id, $data, 0);

    return $size;
}

/**
 * Read data from shared memory block
 * @return bool|mixed
 * @return false for not exists or timeout; if enabled; data otherwise
 */
function read($shmKey)
{
    $id = @shmop_open($shmKey, "a", 0, 0);
    if ($id === false) {
        print("[read] try $shmKey but not found \n");
        return false;
    }

    $data = shmop_read($id, 0, shmop_size($id));

    $data = unpack($data);
    $result = $data['data'];
    $timeout = intval($data['timeout']);

    if ($timeout != 0 && $timeout < microtime()) {
        $result = false; //timeout
    }

    return $result;
}


function getStack()
{
    $callStack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    // concatenate relevant information from the call stack into a string
    $stackString = '';
    foreach ($callStack as $trace) {
        // include relevant information (e.g., function name, class name) in the key
        $stackString .= $trace['function'] . '|';
    }
    return $stackString;
}

function ADDR($idx)
{
    return $idx ^ 0xFFFF0000;
}

function IDX($addr)
{
    return $addr ^ 0xFFFF0000;
}

function findAddr(string $str): array
{
    $matches = [];

    // Define the regex pattern to match decimal numbers
    $pattern = '/\b\d+\b/';

    // Find all matches
    preg_match_all($pattern, $str, $matches);

    // Filter out values in the specified range
    return array_filter($matches[0], function ($match) {
        return ($match >= 0xFFFF0000 && $match <= 0xFFFFFFFF);
    });
}

function extractTableName(string $sql): array
{
    // regular expression for matching UPDATE statement and extracting table name
    $updatePattern = '/UPDATE\s+(\w+)\s+SET/i';

    // perform the match
    if (preg_match($updatePattern, $sql, $matches)) {
        $tableName = $matches[1];
        return array("UPDATE", hexdec(crc32($tableName)));
    }
    // regular expression for matching SELECT statement and extracting table name
    $selectPattern = '/FROM\s+(\w+)/i';

    // perform the match
    if (preg_match($selectPattern, $sql, $matches)) {
        $tableName = $matches[1];
        return array("SELECT", hexdec(crc32($tableName)));
    }
    return array("None", NULL);
}

class PHPTrace
{
    public static array $trace = array();

    public static function sqlPutTrace(string $funcName, array $args): mixed
    {
        #print(var_dump($args));
        self::$trace[] = ["funcName" => $funcName, "args" => $args, "ret" => ADDR(count(self::$trace))];
        return end(self::$trace)["ret"];// return the index
    }

    public static int $bitmapSHMKey = 0xFFFFFFFF;

    /**
     * Redo trace for the last trace element function call
     */
    public static function redoQuery(int $resultIdx): array
    {
        // find $result variable from result fetch
        // first argument
        PHPTrace::dumpTrace();

        $queryCall = self::$trace[$resultIdx];
        $connectIdx = IDX($queryCall["args"][0]);
        $connectCall = self::$trace[$connectIdx];
        $mysqli = call_user_func_array("mysqli_connect", $connectCall["args"]);

        for ($i = $resultIdx - 1; $i > $connectIdx; $i--) {
            // backward search for select_db or use db
            $call = self::$trace[$i];
            if ($call["funcName"] == "mysqli_select_db") {
                mysqli_select_db($mysqli, $call["args"][1]);
                break;
            } else if ($call["funcName"] == "mysqli_query" && strpos($call["args"][1], "USE ") !== false) {
                // XXX check query, embedded values.
                mysqli_query($mysqli, $call["args"][1]);
                break;
            }
        }
        $q = $queryCall["args"][1];
        $query = $q;

        $result = mysqli_query($mysqli, $query);

        $allData = mysqli_fetch_all($result, MYSQLI_BOTH);
        mysqli_close($mysqli);

        return $allData;
    }

    public static function dumpTrace(string $log = ""): void
    {
        $log = "-----\n"
            . $log
            . "\n"
            . getStack() . "\n----\n";
        for ($i = 0; $i < count(self::$trace); $i++) {
            $t = self::$trace[$i];
            $log .= $i . ": " . $t["funcName"] . "("
                . implode(",", $t["args"])
                . ")@"
                . dechex($t["ret"])
                . "\n";
        }
        $customLogFile = "/tmp/php.log";
        error_log($log, 3, $customLogFile);
    }
}


/**
 * record the sql function calls in trace
 */
function sqlWrapperFunc($funcName, $args)
{
    global $BAD_CHARS, $GOOD_CHARS;

    PHPTrace::sqlPutTrace($funcName, $args);

    switch ($funcName) {
        case "mysqli_connect":
            return end(PHPTrace::$trace)["ret"]; // return the index;
        case "mysqli_real_escape_string":
            return str_replace($BAD_CHARS, $GOOD_CHARS, $args[1]);
        case "mysqli_query":
            $table = extractTableName($args[1]);
            $tablehash = $table[1];
            $queryhash = hexdec(crc32($args[1]));
            if ($table[0] === 'SELECT') {
                $table2query = read(PHPTrace::$bitmapSHMKey);
                $table2query = ($table2query !== false) ? $table2query : array();
                if (array_key_exists($tablehash, $table2query)
                    && array_key_exists($queryhash, $table2query[$tablehash])
                    && $table2query[$tablehash][$queryhash] === 1) {
                    // valid, then we can directly return the results;
                    print("[query]: SELECT: valid cache\n");
                } else {
                    $allData = PHPTrace::redoQuery(IDX(end(PHPTrace::$trace)["ret"])); // should give idx of query// default the last one
                    write($queryhash, $allData);
                    $table2query[$tablehash][$queryhash] = 1;
                    write(PHPTrace::$bitmapSHMKey, $table2query);
                    // data is in cache and valid
                    print("[query]: SELECT: invalid cache\n");
                }
            } else if ($table[0] === 'UPDATE' || $table[0] === 'INSERT') {

                $allData = PHPTrace::redoQuery(IDX(end(PHPTrace::$trace)["ret"]));
                write($queryhash, $allData);
                // update 
                $table2query = read(PHPTrace::$bitmapSHMKey);
                if (array_key_exists($tablehash, $table2query)) {
                    foreach ($table2query[$tablehash] as $sql) {
                        $table2query[$tablehash][$sql] = 0;
                    }
                    write(PHPTrace::$bitmapSHMKey, $table2query);
                }
            }

            return end(PHPTrace::$trace)["ret"]; // return the index;

        case "mysqli_close":
        case "mysqli_error":
        case "mysqli_connect_error":
            return true; // usually no return value

        case "mysqli_fetch_assoc":
        case  "mysqli_fetch_array":
        case "mysqli_fetch_row":
        case "mysqli_fetch_all":
        case "mysqli_num_rows":
            $resultIdx = IDX($args[0]);

            $queryhash = hexdec(crc32(PHPTrace::$trace[$resultIdx]["args"][1]));
            $table = extractTableName(PHPTrace::$trace[$resultIdx]["args"][1]);
            $tablehash = $table[1];
            $table2query = read(PHPTrace::$bitmapSHMKey);

            if (array_key_exists($tablehash, $table2query)
                && array_key_exists($queryhash, $table2query[$tablehash])
                && $table2query[$tablehash][$queryhash] === 1) {
                // valid, then we can directly return the results;
                print("[fetch]: valid sql cached\n");
                $allData = read($queryhash);
            } else {
                print("[fetch]: invalid sql and redo!\n");
                $allData = PHPTrace::redoQuery($resultIdx); // should give idx of query
                write($queryhash, $allData);
                $table2query[$tablehash][$queryhash] = 1;
                write(PHPTrace::$bitmapSHMKey, $table2query);
                // data is in cache and valid
            }
            return ($funcName === "mysqli_num_rows") ? count($allData) : $allData;
        case "mysqli_fetch_lengths":
            return array(0);
    }
    return false;
}
