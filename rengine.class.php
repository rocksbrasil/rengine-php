<?php
namespace rengine;
class api{
    private $endpoint, $user, $pass;
    private $appId, $modId, $extId, $filecache;
    private $errorFunc, $lastError = Array('code' => 0, 'msg' => null);
    public $enableCache = true, $internalErrors = false;
    function __construct($endpoint, $user, $password){
        $this->endpoint = $endpoint;
        $this->user = $user;
        $this->pass = md5($password);
        $this->filecache = new filecache();
        $this->filecache->cacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rengine'.DIRECTORY_SEPARATOR;
        return true;
    }
    function setCacheDir($dir){
        if(!is_writable($dir)){
            throw new \Exception("Cache directory '".$dir."' is not writable!");
            return false;
        }
        $this->filecache->cacheDir = $dir;
        return true;
    }
    function app($app){
        return $this->application($app);
    }
    function application($app){
        $this->appId = $app;
        $this->modId = null;
        $this->extId = null;
        return $this;
    }
    function mod($mod){
        return $this->module($mod);
    }
    function module($mod){
        $this->modId = $mod;
        $this->extId = null;
        return $this;
    }
    function ext($ext){
        return $this->extension($ext);
    }
    function extension($ext){
        $this->extId = $ext;
        return $this;
    }
    function __call($func, $params){
        $retorno = false;
        // REALIZAR CONSULTA NO CACHE
        $cacheHash = '';
        $cacheHash .= ($this->appId)? $this->appId.'-' : '';
        $cacheHash .= ($this->modId)? $this->modId.'-' : '';
        $cacheHash .= ($this->extId)? $this->extId.'-' : '';
        $cacheHash .= $func.'-'.md5($this->endpoint.json_encode($params));
        if($this->enableCache && $retorno = $this->filecache->cache_get($cacheHash, $cacheChangeTime)){
            if(isset($retorno['cache_lifetime'])){
                if((time() - $cacheChangeTime) > $retorno['cache_lifetime']){
                    $this->filecache->cache_unset($cacheHash);
                    $retorno = false;
                }
            }else{
                $retorno = false;
            }
        }
        // REALIZAR CONSULTA NO ENDPOINT
        if(!$retorno){
            if($params){
                foreach($params as $key => $value){
                    $params[$key] = @json_encode($value);
                }
            }
            $params['app'] = $this->appId;
            $params['mod'] = $this->modId;
            $params['ext'] = $this->extId;
            $params['func'] = $func;
            if($retorno = $this->request($this->endpoint, $params, $httpCode)){
                if($retornoDecoded = json_decode($retorno, true)){
                    $retorno = $retornoDecoded;
                    // SALVAR CONSULTA EM CACHE
                    if($this->enableCache && isset($retorno['cache_lifetime']) && $retorno['cache_lifetime']){
                        $this->filecache->cache_set($cacheHash, $retorno);
                    }
                }else{
                    return $this->throwError(null, 'Invalid Endpoint Result: HTTP Response Code: '.$httpCode.' Message: '.$retorno);
                }
            }
        }
        // RETORNAR O RESULTADO / ERRO
        if($retorno){
            if(isset($retorno['status']) && $retorno['status'] == 'ok' && array_key_exists('response', $retorno)){// OK
                return $retorno['response'];
            }elseif(isset($retorno['status']) && $retorno['status'] == 'error' && isset($retorno['error']['message'])){
                return $this->throwError($retorno['error']['code'], $retorno['error']['message']);
            }else{
                $eMessage = (isset($retorno['error']['message']))? $retorno['error']['message'] : '';
                $eMessage .= (isset($retorno['status']))? '(Status: '.$retorno['status'].')': '';
                $eMessage .= (isset($retorno['response']))? 'Response: '.$retorno['response']: '';
                $eCode = (isset($retorno['error']['code']))? $retorno['error']['code'] : 'n/a';
                return $this->throwError($eCode, 'Unknown Error['.$eCode.']: '.$eMessage);
            }
        }else{
            return $this->throwError(null, "Invalid Endpoint: ".$this->endpoint);
        }
    }
    private function throwError($code, $message){
        $lastError = Array('code' => $code, 'msg' => $message);
        if($this->errorFunc && is_callable($this->errorFunc)){
            call_user_func($this->errorFunc, $code, $message);
        }
        if(!$this->internalErrors){
            throw new \Exception($message, $code);
        }
        return false;
    }
    function errorHandler($function){
        if(is_callable($function)){
            $this->errorFunc = $function;
            return true;
        }else{
            $this->errorFunc = null;
            return false;
        }
    }
    private function request($endpoint, $data, &$httpCode = 0){
        $data['user'] = $this->user;
        $data['pass'] = $this->pass;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,5); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); //timeout in seconds
        $retorno = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $retorno;
    }
}
