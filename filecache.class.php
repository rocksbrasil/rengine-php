<?php
namespace rengine;
class filecache{
    public $cacheDir, $cacheExt = 'fcache', $cacheLifetime = 1800;
    function __construct(){
        $this->cacheDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'filecache'.DIRECTORY_SEPARATOR;
    }
    function cache_set($key, $data){
        $data = var_export($data, true);
        if(!@file_put_contents($this->cacheDir.DIRECTORY_SEPARATOR.$key.'.'.$this->cacheExt, $data, LOCK_EX)){
            if(!file_exists($this->cacheDir)){
                @mkdir($this->cacheDir, 0755, true);
            }
            return false;
        }
        return true;
    }
    function cache_exists($key, &$fileChangeTime = false){
        if($fileStat = @stat($this->cacheDir.DIRECTORY_SEPARATOR.$key.'.'.$this->cacheExt)){
            $fileChangeTime = $fileStat['mtime'];
            if((time() - $fileStat['mtime']) <= $this->cacheLifetime){
                return true;
            }else{
                $this->cache_unset($key);
            }
        }
        return false;
    }
    function cache_get($key, &$fileChangeTime = false){
        if($file = @fopen($this->cacheDir.DIRECTORY_SEPARATOR.$key.'.'.$this->cacheExt, 'r')){
            $fileStat = @fstat($file);
            $fileChangeTime = $fileStat['mtime'];
            if($fileStat && (time() - $fileStat['mtime']) <= $this->cacheLifetime){
                if($data = fread($file, $fileStat['size'])){
                    eval('$data = '.$data.';');
                }
                fclose($file);
                return $data;
            }else{
                fclose($file);
                $this->cache_unset($key);
            }
        }
        return null;
    }
    function cache_unset($key){
        @chmod($this->cacheDir.DIRECTORY_SEPARATOR.$key.'.'.$this->cacheExt, 0777);
        return @unlink($this->cacheDir.DIRECTORY_SEPARATOR.$key.'.'.$this->cacheExt);
    }
    function cache_clear(){
        array_map(function($file){
            @chmod($file, 0777);
            @unlink($file);
        }, glob($this->cacheDir.DIRECTORY_SEPARATOR."*.".$this->cacheExt));
        return true;
    }
}
