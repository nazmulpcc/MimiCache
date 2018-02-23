<?php

namespace nazmulpcc;

class MimiCache
{
    public static $prefix;

    protected static $path = __DIR__ . '/../cache/store';

    protected static $tagPath = __DIR__ . '/../cache/tags';

    protected static $time = 5*60;

    protected static $instance;

    protected static $tag = '';

    protected static $secret = 'abcdefghijklmnop';  // must be at least 16 bit long

    protected static $encodeMethod = "AES-256-CBC";

    private function __construct (){}

    public static function instance ()
    {
        if(!isset(static::$instance)){
            static::$instance = new static();
        }
        return static::$instance;
    }

    public static function get( $key, $default = null, $overwrite = false ){
        if (static::valid($key) && $overwrite == false){
            return static::read($key);
        }else{
            if (is_callable($default)){
                $default = call_user_func($default);
            }
            if ($default !== null)
                static::set($key, $default);
            return $default;
        }
    }

    public static function set( $key, $data, $tag = '' ){
        $written = static::write($key, $data) > 0;
        if($written && $tag != ''){
            static::addTag($key, $tag);
        }
        return $written;
    }

    public static function remove( $key ){
        if (static::exists($key)){
            if(unlink(static::getPath($key)));
                return true;
        }else{
            return false;
        }
    }

    public static function delete ( $key )
    {
        return static::remove($key);
    }

    public static function valid( $key ){
        return static::exists($key) && static::age($key) < static::$time;
    }

    public static function exists( $key ){
        return file_exists(static::getPath($key));
    }

    public static function age($key){
        return time() - filemtime(static::getPath($key));
    }

    protected static function encode( $key ){
        $method = static::$encodeMethod;
        $secret = static::$secret;
        $key = static::$prefix.$key;
        return base64_encode(openssl_encrypt($key, $method, $secret, 0, $secret));
    }

    protected static function decode($key){
        $method = static::$encodeMethod;
        $secret = static::$secret;
        return openssl_decrypt(base64_decode($key), $method, $secret, 0, $secret);
    }

    protected static function getPath( $key ){
        return static::$path . '/' . static::encode($key);
    }

    protected static function read( $key ){
        return unserialize(file_get_contents(static::getPath($key)));
    }

    protected static function write( $key, $data ){
        static::remove($key);
        return file_put_contents(static::getPath($key), serialize($data));
    }

    /**
     * Set a prefix for $key
     * @param $prefix
     * @return mixed
     */
    public static function prefix ( $prefix )
    {
        static::$prefix = $prefix;
        return static::instance();
    }

    /**
     * Set the path where values will be stored
     * @param $path
     * @return mixed
     */
    public static function path ( $path )
    {
        static::$path = $path;
        return static::instance();
    }

    /**
     * Set the validity time in seconds
     * @param int $time
     * @return mixed
     */
    public static function time ( $time = 600 )
    {
        static::$time = $time;
        return static::instance();
    }

    public static function addTag ( $key, $tag )
    {
        $path = static::getPath($key);
        $tagPath = static::getTagPath( $tag) . '/' . static::encode($key);
        if (!file_exists($tagPath)){
            return symlink($path, $tagPath);
        }else{
            return true;
        }
    }

    public static function removeTag( $key, $tag )
    {
        $tagPath = static::getTagPath( $tag ) . '/' . static::encode($key);
        return unlink($tagPath);
    }

    public static function getTagPath ( $tag )
    {
        $tagPath = static::$tagPath . '/' . static::encode($tag);
        if (!file_exists($tagPath)){
            mkdir($tagPath, '0777', 1);
        }
        return $tagPath;
    }

    public static function tagged ($tag, $limit = 100)
    {
        $tagPath = static::getTagPath($tag);
        $tagged = scandir($tagPath);
        unset($tagged[0]);
        unset($tagged[1]);
        $data = [];
        foreach ($tagged as $item){
            $key = static::decode($item);
            $value = static::get($key);
            $data[$key] = $value;
        }
        return $data;
    }

}