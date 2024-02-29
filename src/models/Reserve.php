<?php
namespace aic\models;

use aic\models\Member;
use aic\models\RsvSample;
use aic\models\RsvMember;
use aic\models\Util;

class Reserve extends Model{
    protected $table = "tb_reserve";
    protected $inst_table = 'tb_instrument';
    protected $member_table = 'tb_member';
    
    function getDetail($id)
    {
        $rsv = parent::getDetail($id);        
        if (!$rsv){ // prepare a dummy reservation for insertion  
            $filefds = $this->getFileds();
            foreach ($filefds as $f){
                $key = $f['Field'];
                $rsv[$key] = '';
            }
            $rsv['id'] = 0;
            $rsv['apply_mid'] = 1; //$_SESSION['member_id'];
            $rsv['xray_chk'] = 0;
            $rsv['apply_member'] = (new Member)->getDetail($rsv['apply_mid']);
            $rsv['rsv_member'] = $rsv['sample_nature'] = [];  
            $rsv['sample_other']='';    
            $rsv['sample_state']=1;
            $rsv['stime'] = $rsv['etime'] = date('Y-m-d H:i');
            return $rsv;
        }
        // real reservation for edit
        $instrument = (new Instrument)->getDetail($rsv['instrument_id']); 
        $rsv['instrument_name'] = $instrument['fullname'];
        $rsv['apply_member'] = (new Member)->getDetail($rsv['apply_mid']);
        $rsv['master_member'] = (new Member)->getDetail($rsv['master_mid']);
        $_dept_code = $rsv['master_member']['dept_code'];
        $rsv['dept_name'] = KsuCode::FACULTY_DEPT[$_dept_code];

        $rsv['rsv_member'] = (new RsvMember)->getList('reserve_id='.$id);
        $students = array_filter($rsv['rsv_member'], function($a){ return $a['category']==1; });
        $rsv['student_n'] = count($students);
        $rsv['staff_n'] = count($rsv['rsv_member'])- count($students); 

        $rsv['sample_state_str'] = KsuCode::SAMPLE_STATE[$rsv['sample_state']];
        $rsv['xray_chk_str'] = KsuCode::YESNO[$rsv['xray_chk']]; 

        $samples = (new RsvSample)->getList('reserve_id='.$id);
        $selected = [];
        $other = '';
        foreach ($samples as $sample){
            $selected[] = $sample['nature'];
            if ($sample['nature'] == 4) $other = $sample['other'];
        }
        $rsv['sample_other'] = $other;
        $rsv['sample_nature'] = $selected;
        $_natures = Util::array_slice_by_index(KsuCode::SAMPLE_NATURE, $selected);
        $rsv['sample_nature_str'] = implode(', ', $_natures);
        $status = $rsv['status'];
        $rsv['status_name'] = KsuCode::RSV_STATUS[$status];
        return $rsv;
    }

     // $inst_id= 0 for all, or 1~ for one specific instrument 
    // $status=9 for all, or 1~ for one specific status
    function getNumRows($inst_id=0, $date1=null, $date2=null, $status=9)
    {
        $conn = $this->db; 
        $sql = "SELECT *  FROM %s WHERE 1 ";
        $sql = sprintf($sql, $this->table, $this->inst_table, $this->member_table, $this->member_table);
        if ($inst_id){  
            $sql .= " AND instrument_id=$inst_id"; 
        }
        if ($date1 and $date2){
            $sql .= " AND GREATEST(stime, '{$date1} 00:00') <= LEAST(etime, '{$date2} 23:59')"; 
        }elseif($date1 and !$date2){
            $sql .= " AND etime >= '{$date1}'";
        }
        if ($status < 9){ 
            $sql .= " AND status=$status"; 
        }
        $rs = $conn->query($sql);
        if (!$rs) die('エラー: ' . $conn->error);
        return $rs->num_rows;
    }

   
    function getListByInst($inst_id=0, $date1=null, $date2=null, $status=9, $page=0)
    {
        $conn = $this->db; 
        $sql = "SELECT r.*, f.fullname, f.shortname,m1.ja_name AS apply_name, m2.ja_name AS master_name
          FROM %s r, %s f, %s m1, %s m2 WHERE r.apply_mid=m1.id AND r.master_mid=m2.id AND f.id=r.instrument_id ";
        $sql = sprintf($sql, $this->table, $this->inst_table, $this->member_table, $this->member_table);
        if ($inst_id){ 
            $sql .= " AND r.instrument_id=$inst_id"; 
        }
        if ($date1 and $date2){
            $sql .= " AND GREATEST(stime, '{$date1} 00:00') <= LEAST(etime, '{$date2} 23:59')"; 
        }elseif($date1 and !$date2){
            $sql .= " AND etime>'{$date1}'";
        }
        if ($status < 9){ 
            $sql .= " AND r.status=$status"; 
        }
        $sql .= ' ORDER BY instrument_id, stime, etime';
        if ($page>0){
            $n = KsuCode::PAGE_ROWS;
            $sql .= sprintf(' LIMIT %d OFFSET %d', $n, ($page-1) * $n);
        }
        // echo $sql;
        $rs = $conn->query($sql);
        if (!$rs) die('エラー: ' . $conn->error);
        return $rs->fetch_all(MYSQLI_ASSOC);
    }
      
    function getItems($inst_id, $date1=null, $date2=null)
    {
        $rows = $this->getListByInst($inst_id, $date1, $date2);
        return self::toItems($rows);
    }

    static function toItems($rows)
    {
        $items = [];
        foreach ($rows as $row){
            $e = isset($row['status']) ? $row['status'] : 1;
            $items[] = [
              'id' => $row['id'],
              'group'=>$row['instrument_id'],
              'title'=>$row['purpose'] .'（'. KsuCode::RSV_STATUS[$e] . '）'. $row['master_name'],
              'className'=> isset(KsuCode::RSV_STYLE[$e]) ? KsuCode::RSV_STYLE[$e] : 'black', 
              'start'=> $row['stime'],
              'end'=> $row['etime'],
            ];
        }
        return $items;
    }
}
