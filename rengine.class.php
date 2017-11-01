<?php
namespace rengine;
class api{
    private $endpoint, $filecache;
    private $appId, $modId, $extId;
    public $enableCache = true;
    function __construct($endpoint){
        $this->endpoint = $endpoint;
        $this->filecache = new filecache();
        return true;
    }
    function app($app){
        return $this->application($app);
    }
    function application($app){
        $this->appId = $app;
        return $this;
    }
    function mod($mod){
        return $this->module($mod);
    }
    function module($mod){
        $this->modId = $mod;
        return $this;
    }
    function ext($ext){
        return $this->extension($mod);
    }
    function extension($ext){
        $this->extId = $ext;
        return $this;
    }
    function __call($func, $params){
        $retorno = false;
        // REALIZAR CONSULTA NO CACHE
        $cacheHash = $func.'-'.md5($this->endpoint.json_encode($params));
        if($this->enableCache && $this->filecache->cache_exists($cacheHash)){
            $retorno = $this->filecache->cache_get($cacheHash);
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
                if($httpCode == 200){
                    // SALVAR CONSULTA EM CACHE
                    if($this->enableCache){
                        $this->filecache->cache_set($cacheHash, $retorno);
                    }
                }
            }
            
        }
        // RETORNAR O RESULTADO / ERRO
        if($retorno){
            $retorno = json_decode($retorno, true);
            if(isset($retorno['status']) && $retorno['status'] == 'ok' && isset($retorno['response'])){// OK
                return $retorno['response'];
            }elseif(isset($retorno['status']) && $retorno['status'] == 'error' && isset($retorno['error']['message'])){
                throw new \Exception($retorno['error']['message'], $retorno['error']['code']);
            }else{
                throw new \Exception("fail");
            }
        }
    }
    private function request($endpoint, $data, &$httpCode = 0){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $retorno = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $retorno;
    }
}
