<?php

#-------------------------------------------------------------------------------
#                                   Basic
#-------------------------------------------------------------------------------

error_reporting(E_ALL);
setlocale(LC_ALL, 'en_US');
date_default_timezone_set('UTC');


function debug($value)
# Writes a log entry to the webserver error log
{
  error_log(to_string($value));
}


function raise_error($msg, $level = 0)
# Raises a fatal error (aborts execution)
{
  $Trace = debug_backtrace();
  $location = basename($Trace[$level]['file']) . ':'. $Trace[$level]['line'];
  $msg .= ' (location: ' . $location . ')';
  trigger_error($msg, E_USER_ERROR);
}


function array_value($array, $index, $defaultValue = null)
# Return the value of an array.
# Returns value of $defaultValue if the index does not exist.
{
  if (is_array($array) && array_key_exists($index, $array))
    return $array[$index];
  return $defaultValue;
}


function array_value_by_index($array, $index)
# Return the value of an associative array by index.
{
  $values = array_values($array);
  return array_value($values, $index);
}


function array_key_by_index($array, $index)
# Return the key of an associative array by index.
{
  $values = array_keys($array);
  return array_value($values, $index);
}



#-------------------------------------------------------------------------------
#                                 String processing
#-------------------------------------------------------------------------------

function to_string($value)
# Formats a boolean/string/number/array as readable string.
{
  if ($value === null)
    return 'null';
  if ($value === true)
    return 'true';
  if ($value === false)
    return 'false';
  if (is_numeric($value))
    return $value;
  if (is_array($value))
  {
    $result = '';
    foreach ($value as $Key => $Val)
    {
      if ($result != '')
        $result = $result.', ';
      $result = $result . to_string($Key) . ': ' . to_string($Val);
    }
    return '{' . $result . '}';
  }
  if (is_object($value))
  {
    if (method_exists($value, '__toString'))
      return $value->__toString();
    return 'object of ' . get_class($value);
  }
  # string:
  $value = str_replace("\n", '\n', $value);
  $value = str_replace("\r", '\r', $value);
  return '\'' . $value . '\'';
}


function contains($haystack, $needle)
{
  return strpos($haystack, $needle) !== false;
}


function starts_with($haystack, $needle)
{
  $length = strlen($needle);
  return substr($haystack, 0, $length) === $needle;
}


function ends_with($haystack, $needle)
{
  $length = strlen($needle);
  if ($length == 0)
    return true;
  return substr($haystack, -$length) === $needle;
}


function generate_token()
{
  return sha1(microtime()) . dechex(mt_rand());
}


function limit_str_length($str, $maxLength, $ellipsisStr = '...')
{
  if (mb_strlen($str) > $maxLength)
    return mb_substr($str, 0, $maxLength).$ellipsisStr;
  else
    return $str;
}


function limit_str_length_html($str, $maxLength, $ellipsisStr = '...')
{
  return limit_str_length($str, $maxLength, '&hellip;');
}


function clean_whitespaces($str)
# Removes whitespaces at begin and end and removes consecutive whitespaces
{
  return preg_replace('/\s+/', ' ', trim($str));
}


#-------------------------------------------------------------------------------
#                               Request related
#-------------------------------------------------------------------------------

function get_client_ip_address()
{
  $x = getenv('HTTP_X_FORWARDED_FOR');
  if ($x != '')
    return $x; # In case of a load balancer
  return getenv('REMOTE_ADDR');
}


function is_https()
{
  return array_value($_SERVER, 'HTTPS') != null;
}


function get_param_value($name, $defaultValue = null)
# Returns value of GET or POST paramter by name.
# If the parameter does not exist or is an empty string, $defaultValue will be returned.
{
  $value = array_value($_POST, $name);
  if ($value === null)
  {
    $value = array_value($_GET, $name);
    if ($value !== null)
      $value = urldecode($value);
  }
  if ($value !== null)
    return $value;
  return $defaultValue;
}


function get_current_url_params($paramName = '', $paramValue = '')
# Returns part after '?' from current URL including '?'.
# The parameter with the name $paramName will get the value $paramValue.
{
  $result = '?';
  foreach ($_GET as $name => $value)
  {
    if ($name == $paramName)
      $value = $paramValue;
    if ($value === true)
      $value = 'true';
    if ($value != ''  || $value === 0)
    {
      if ($result != '')
        $result = $result.'&';
      $result = $result.$name.'='.urlencode($value);
    }
  }
  return $result;
}


function get_cookie($key)
{
  $result = array_value($_COOKIE, $key);
  if ($result == 'true')
    $result = true;
  elseif ($result == 'false')
    $result = false;
  return $result;
}


function set_cookie($key, $value, $expire)
# $expire: timestamp or null for session only
{
  setcookie($key, $value, $expire, '', '', is_https(), true);
  $_COOKIE[$key] = $value;
}


function get_os_from_user_agent($userAgent)
# Parses the operating system from a user agent string
{
  if (!starts_with($userAgent, 'Mozilla/5.0 ('))
    return;
  $str = substr($userAgent, 13);
  if (starts_with($str, 'Windows NT'))
    return 'Windows';
  if (starts_with($str, 'Windows Phone'))
    return 'Windows Phone';
  if (starts_with($str, 'Macintosh'))
    return 'macOS';
  if (starts_with($str, 'X11'))
    return 'Linux';
  if (starts_with($str, 'iPhone') || starts_with($str, 'iPad'))
    return 'iOS';
  if (contains($str, 'Android'))
    return 'Android';
}


function user_agent_is_not_supported()
# Parses the operating system from a user agent string
{
  $userAgent = array_value($_SERVER, 'HTTP_USER_AGENT');
  return contains($userAgent, 'Trident/');
}



#-------------------------------------------------------------------------------
#                                   Date & Time
#-------------------------------------------------------------------------------

function date_time_to_timestamp($str)
# Converts a date time string to a unix timestamp.
# In case of a failure it returns false.
{
  if ($str == null)
    return null;
  return strtotime($str);
}


function format_timestamp($timestamp, $format = '%Y-%m-%d %H:%M:%S')
# Converts a unix timestamp to a formated string.
# Use a 'X' in front of a number to remove a leading zero.
{
  if ($timestamp === null)
    return null;
  $str = strftime($format, $timestamp);
  $str = str_replace('X0', '', $str);
  $str = str_replace('X', '', $str);
  $str = utf8_encode($str);
  return $str;
}


function timestamp_floor_hour($timestamp)
# $timestamp: timestamp
# Result: timestamp with time xx:00
{
  return strtotime(date('Y-m-d H:00:00', $timestamp));
}


function timestamp_floor_day($timestamp)
# $timestamp: timestamp
# Result: timestamp with time 00:00
{
  return strtotime(date('Y-m-d', $timestamp));
}


function timestamp_floor_week($timestamp)
# $timestamp: timestamp
# Result: timestamp on Monday, 00:00
{
  return strtotime(date('Y-m-d', $timestamp - (date('N', $timestamp) - 1) * 24 * 60 * 60));
}


function timestamp_floor_month($timestamp)
# $timestamp: timestamp
# Result: timestamp with 1. day of month, 00:00
{
  return strtotime(date('Y-m-01', $timestamp));
}


function timestamp_floor_year($timestamp)
# $timestamp: timestamp
# Result: timestamp with 1. day of month, 00:00
{
  return strtotime(date('Y-01-01', $timestamp));
}


function timestamp_floor($timestamp, $precision)
# $timestamp: timestamp
# $precision: 'hour', 'day', 'week', 'month' or 'year'
# Result: timestamp floored
{
  if ($precision == 'hour')
    return timestamp_floor_hour($timestamp);
  if ($precision == 'day')
    return timestamp_floor_day($timestamp);
  if ($precision == 'week')
    return timestamp_floor_week($timestamp);
  if ($precision == 'month')
    return timestamp_floor_month($timestamp);
  if ($precision == 'year')
    return timestamp_floor_year($timestamp);
}



#-------------------------------------------------------------------------------
#                                   HTML output
#-------------------------------------------------------------------------------

function html_encode($text)
# Encodes a plain text as HTML code
{
  if ($text === null)
    return 'null';
  if ($text === true)
    return 'true';
  if ($text === false)
    return 'false';
  return htmlentities($text, ENT_COMPAT, 'UTF-8');
}


function html_node($tagName, $content = '', $attributes = [])
# Returns an HTML node (opening tag, content and closing tag)
{
  $s = '<';
  $s .= $tagName;
  foreach ($attributes as $key => $value)
  {
    if ($value !== null)
    {
      $s .= ' ';
      $s .= $key;
      if ($value !== true)
      {
        $s .= '="';
        $s .= html_encode($value);
        $s .= '"';
      }
    }
  }
  if ($content !== null)
  {
    $s .= '>';
    $s .= $content;
    $s .= '</';
    $s .= $tagName;
    $s .= '>';
  }
  else
    $s .= ' />';
  return $s;
}


function html_open($tagName, $attributes = [])
# Returns an opening HTML tag
{
  $s = '<';
  $s .= $tagName;
  foreach ($attributes as $key => $value)
  {
    if ($value !== null)
    {
      $s .= ' ';
      $s .= $key;
      if ($value !== true)
      {
        $s .= '="';
        $s .= html_encode($value);
        $s .= '"';
      }
    }
  }
  $s .= '>';
  return $s;
}


function html_close($tagName)
# Returns a closing HTML tag
{
  $s = '</';
  $s .= $tagName;
  $s .= '>';
  return $s;
}


function html_a($url, $content = '', $attributes = [])
{
  if ($content == '')
    $content = $url;
  $attributes['href'] = $url;
  return html_node('a', $content, $attributes);
}


function html_input($type, $name = null, $value = null, $attributes = [])
{
  $attributes['type'] = $type;
  if ($name != null)
    $attributes['name'] = $name;
  if ($value != null)
    $attributes['value'] = $value;
  return html_node('input', null, $attributes);
}


function html_checkbox($name = null, $checked = false, $attributes = [])
{
  $attributes['type'] = 'checkbox';
  if ($name != null)
    $attributes['name'] = $name;
  if ($checked)
    $attributes['checked'] = true;
  return html_node('input', null, $attributes);
}


function html_textarea($name = null, $value = '', $attributes = [])
{
  if ($name != null)
    $attributes['name'] = $name;
  if ($value === null)
    $value = '';
  return html_node('textarea', $value, $attributes);
}


function html_button($content, $attributes = [])
{
  $attributes['type'] = 'button'; # avoid to act as submit button
  return html_node('button', $content, $attributes);
}

function html_form_submit_button($content, $attributes = [])
{
  return html_node('button', $content, $attributes);
}

function html_redirect_button($url, $content, $attributes = [])
{
  $attributes['onclick'] = js_redirect($url);
  $attributes['onmousedown'] = 'openUrlInNewTabOnMiddleClick(event, "' . $url . '");';
  return html_button($content, $attributes);
}


function write_css_include_tag($path)
{
  echo '<link rel="stylesheet" type="text/css" href="';
  echo $path;
  echo '?';
  echo filemtime(dirname(__FILE__) . '/' . $path);
  echo '" />';
}


function write_script_include_tag($path)
{
  echo '<script src="';
  echo $path;
  echo '?';
  echo filemtime(dirname(__FILE__) . '/' . $path);
  echo '"></script>';
}


function array_to_html($data)
{
  return nl2br(json_encode($data, JSON_PRETTY_PRINT));
}



#-------------------------------------------------------------------------------
#                                 JavaScript output
#-------------------------------------------------------------------------------

function js_redirect($url)
# Returns a JavaScript statement to redirect to a new URL
{
  return 'location.href = "' . $url . '";';
}



#-------------------------------------------------------------------------------
#                                  Database access
#-------------------------------------------------------------------------------

class db_writer
{
  protected $pdo;

  public function __construct($config)
  # keys of $config:
  # - $dbType
  # - $dbName
  # - $dbHost (optional)
  # - $dbUsername (optional)
  # - $dbPassword (optional)
  {
    $dbType = $config['dbType'];
    $dbName = $config['dbName'];
    $dbHost = array_value($config, 'dbHost');
    $dbUsername = array_value($config, 'dbUsername');
    $dbPassword = array_value($config, 'dbPassword');
    if ($dbHost == '')
      $dsn = $dbType . ':' . $dbName;
    else
      $dsn = $dbType . ':host=' . $dbHost . ';dbname=' . $dbName;
    $this->pdo = new PDO($dsn, $dbUsername, $dbPassword);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    # $this->pdo->exec('SET TIME_ZONE="+00:00";');
  }

  public function insert($tableName, $values)
  # Returns the id of the inserted row on success.
  {
    $sql = 'INSERT INTO ';
    $sql .= $tableName;
    $sql .= ' (';
    $sql .= implode(', ', array_keys($values));
    $sql .= ') VALUES (';
    $first = true;
    foreach ($values as $key => $value)
    {
      if ($first)
        $first = false;
      else
        $sql .= ', ';
      $sql .= '?';
    }
    $sql .= ')';
    $stmt = $this->pdo->prepare($sql);
    if ($stmt->execute(array_values($values)))
      return $this->pdo->lastInsertId();
  }

  public function update_by_id($tableName, $id, $values)
  # Updates exactly one row.
  {
    if ($this->try_update_by_id($tableName, $id, $values) != 1)
      raise_error('Affected row count != 1', 1);
  }

  public function try_update_by_id($tableName, $id, $values)
  # Executes an update statement and returns the number of affected rows.
  {
    $sql = 'UPDATE ';
    $sql .= $tableName;
    $sql .= ' SET ';
    $first = true;
    foreach ($values as $key => $value)
    {
      if ($first)
        $first = false;
      else
        $sql .= ', ';
      $sql .= $key;
      $sql .= '=?';
    }
    $sql .= ' WHERE id=?';
    $stmt = $this->pdo->prepare($sql);
    $parameters = array_values($values);
    $parameters[] = $id;
    $stmt->execute($parameters);
    return $stmt->rowCount();
  }

  public function query_rows($sql, $values = [])
  # Returns an array of associative arrays on success.
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll();
  }

  public function query_row($sql, $values = [])
  # Queries exactly one row and returns it as associative array.
  {
    $result = $this->try_query_row($sql, $values);
    if ($result === false)
      raise_error('Query returned no row', 1);
  }

  public function try_query_row($sql, $values = [])
  # Queries exactly one row or none rows. Returns an associative array or null.
  {
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($values);
    $result = $stmt->fetch();
    if ($stmt->fetch() !== false)
      raise_error('Query returned more than one row', 1);
    return $result;
  }

  public function query_row_by_id($tableName, $id)
  # Queries exactly one row by id and returns it as associative array.
  {
    $result = $this->try_query_row_by_id($tableName, $id);
    if ($result == null)
      raise_error('Query returned no row', 1);
  }

  public function try_query_row_by_id($tableName, $id)
  # Queries exactly one row by id or none rows. Returns an associative array or null.
  {
    $sql = 'SELECT * FROM ';
    $sql .= $tableName;
    $sql .= ' WHERE id = ?';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    $result = $stmt->fetch();
    if ($stmt->fetch() !== false)
      raise_error('Query returned more than one row', 1);
    return $result;
  }

  public function query_value($sql, $values = [])
  # Returns one value on success.
  {
    return array_value_by_index($this->try_query_row($sql, $values), 0);
  }

  public function quote($term)
  {
    return $this->pdo->quote($term);
  }

}


function sql_and($A, $B)
# Return a SQL term of two terms by AND operation, one terms may be empty
{
  if ($A == null)
    return $B;
  if ($B == null)
    return $A;
  return '(' . $A . ') AND (' . $B . ')';
}
