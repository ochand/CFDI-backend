<?php
/**
 * Description of DbWhere
 *
 * @author Edgar
 */
class DbCriteria {
    
    
    private $str;
    
    
    function equal($key, $val, $op=' AND '){
        
        $op = (empty($this->str)) ?  '' : $op;
        
        if($val==null || $val =='')
            return false;
        
        $val = (is_numeric($val)) ? $val : "'$val'";
        
        $this->str .= "$op $key = $val";
        return true;
    }
    
    
    function between($key, $val1, $val2, $op=' AND '){
        
        $op = (!empty($this->str)) ? $op : '';
        
        if($val1=='' || $val1==null || $val2=='' || $val2==null)
            return false;
        
        //$val1 = (is_numeric($val1)) ? $val1 : "'$val1";
        //$val2 = (is_numeric($val2)) ? $val2 : "'$val2'";
        
        $this->str .= "$op $key between '$val1' AND '$val2'";
        return true;
    }
    
    function like ($op, $key, $val, $op=' AND '){
        
        $op = (!empty($this->str)) ? $op : '';
        
        if($val =='' || $val==null)
            return false;
        
        $this->str .= "$op $key like '%$val%'";
        return true;
    } 
    
    
    function in ($op, $key, $aVal, $op=' AND '){
        
        $op = (!empty($this->str)) ? $op : '';
        
        if(count($aVal)==0 || $aVal==null || $aVal=='')
            return false;
        
        $this->str .= "$op $key in ('" . implode("','", $aVal) ."')";
        
        return true;
        
    }
    
    
    function getQuery(){
        if ($this->str=='')
            return '';
        
        return 'where ' . $this->str;        
        
    }
    
}