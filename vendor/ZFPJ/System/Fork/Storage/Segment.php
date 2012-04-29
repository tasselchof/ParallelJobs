<?php

namespace ZFPJ\System\Fork\Storage;

class Segment implements StorageInterface
{
    /**
     * identifier
     * @var string
     */
    protected $identifier;
    
    /**
     *
     * @var mixed 
     */
    protected $memory;
    
    /**
     * Bloc size
     * @var int 
     */
    protected $segmentSize = 256;
    
    /**
     * Bloc size
     * @var int 
     */
    protected $blocSize = 8;
    
    /**
     * Construct segment memory
     * @param type $identifier 
     */
    public function __construct($identifier = 'Z')
    {
        $this->identifier = $identifier;
    }
    
    /**
     * Memory alloc
     */
    public function alloc()
    {
        $this->memory = shmop_open(ftok(__FILE__, $this->identifier), "c", 0644, $this->segmentSize);
    }
    
    /**
     * Read fork uid
     * @param int
     */
    public function read($uid)
    {   
        if(!$this->memory) {
            $this->alloc();
        }
        $str = shmop_read($this->memory, $uid*$this->blocSize, $this->blocSize);
        return trim($str);
    }
    
    /**
     * Write fork uid
     * @param int
     */
    public function write($uid, $str)
    {   
        if(!$this->memory) {
            $this->alloc();
        }
        $str = str_pad($str, $this->blocSize);
        return shmop_write($this->memory, $str, $uid*$this->blocSize);
    }
    
    /**
     * Close segment
     * @param int
     */
    public function close()
    {   
        if(!$this->memory) {
            return;
        }
        shmop_close($this->memory);
        $this->memory = null;
    }
    
    /**
     * Get max bloc allow
     */
    public function max()
    {
        return floor($this->segmentSize/$this->blocSize);
    }
    
    /**
     * Get segment memory
     * @return type 
     */
    public function getSegment()
    {
        return $this->memory;
    }
    
    /**
     * Get bloc size
     * @return int 
     */
    public function getBlocSize()
    {
        return $this->blocSize;
    }
    
    /**
     * Set bloc size
     * @param int 
     */
    public function setBlocSize($size)
    {
        $this->blocSize = $size;
        return $this;
    }
    
    /**
     * Get segment size
     * @return int 
     */
    public function getSegmentSize()
    {
        return $this->segmentSize;
    }
    
    /**
     * Set segment size
     * @param int 
     */
    public function setSegmentSize($size)
    {
        $this->segmentSize = $size;
        return $this;
    }
}

?>