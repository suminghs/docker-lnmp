<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use App\Model\Qc\QcUser;
use App\Model\Qc\QcCache;
use App\Model\Qc\Patient;
use App\Model\Qc\user;
use Illuminate\Support\Facades\DB;

/**
 * Class HomeController
 * @package App\Api\V1
 * @author xlong
 */
class HomeController extends BaseController {

    public function test(){
        $patient = new Patient();
        // $res = user::where('role',2)->where('role_id','1')->first();
        // 查询所有医院信息
        $res = $patient->selectList(187);
//                var_dump($res);die;
        foreach ($res as $k => $v) {
        	$month = date('Y-m',time());
        	$orgid = $v->orgid;
        	// 医院对应的所有月份
        	$all_month = DB::select('SELECT left(bein_time,7) as month,count(*) as num FROM `patient` p LEFT JOIN medical_history m on p.id = m.pid where is_del =0 and orgid = ? group by month where `month`>2016-01',[$orgid]);
        	foreach ($all_month as $kk => $vv) {
        		// 月份对应的指标信息
        		// 分母-当月住院的心衰患者
        		$mon = $vv->month;
                $where = array(
                    'orgname'=>$v->org,
                    'province'=>'',
                    'city'=>'',
                    'rz_type'=>'',
                    'phase'=>'',
                    'category'=>'1',
                    // 'title'=>'1',//诊断使用超声心动图
                    // 'num'=>$deno->num,
                    // 'rate'=>$echocardiography_rate,
                    'year'=>'',
                    'month'=>$mon
                	);
                // 1、诊断使用超声心动图
                $index_1  = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('detection_result','patient.id','detection_result.pid')
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where('patient.orgid',$orgid)
                    ->where('patient.is_del','0')
                    ->where('detection_result.is_chaox','1')
                    ->where('medical_history.bein_time','like',$mon.'%')
                    ->first();
                // var_dump(DB::getQueryLog());die();  //输出sql
                $echocardiography_rate = round($index_1->num/$vv->num*100,2);
                $where['title']='1';
                $where['num']=$vv->num;
                $where['rate']=$echocardiography_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'1','category'=>'1'])->exists()) {
                    unset($where['orgname']);
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'1','category'=>'1'])->update($where);
                }else{
                    $insert = QcCache::insert($where);
                }

                // 2、诊断使用BNP或NT-proBNP
                $index_2 = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('detection_result','patient.id','detection_result.pid')
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['patient.is_del'=>'0',['patient.orgid','=',$orgid],['result_bnp','!=',''],['result_bnp_unit','!=','0'],['medical_history.bein_time','like',$mon.'%']])
                    ->orwhere(['patient.is_del'=>'0',['patient.orgid','=',$orgid],['nt_pro','!=',''],['nt_pro_unit','!=','0'],['medical_history.bein_time','like',$mon.'%']])
                    ->first();
                 $Bnp_rate = round($index_2->num/$vv->num*100,2);
                 $where['title']='2';
                 $where['num']=$vv->num;
                 $where['orgname'] = $v->org;
                 $where['rate']=$Bnp_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'2','category'=>'1'])->exists()) {
                    unset($where['orgname']);
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'2','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 3、出院前HFREF患者使用ACEI/ARB/ARNI
                $index_3_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('detection_result','patient.id','detection_result.pid')
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['patient.is_del'=>'0',['patient.orgid','=',$orgid],['shex_score','<','40'],['medical_history.bein_time','like',$mon.'%']])
                    ->first();
                // 分子
                $index_3 = DB::select('select count(*) as num from `patient` left join `detection_result` on `patient`.`id` = `detection_result`.`pid` left join `medical_history` on `patient`.`id` = `medical_history`.`pid` left join `therapeutic_drugs` on `patient`.`id` = `therapeutic_drugs`.`pid` where (`patient`.`is_del` = 0 and `patient`.`orgid` = ? and `shex_score` < 40 and `medical_history`.`bein_time` like ?) and (`is_acei` = 1 or `is_arb` = 1 or `is_arni` = 1)',[$orgid,$mon.'%']);
                // var_dump(DB::getQueryLog());die();
                $index_3_total->num = empty($index_3_total->num)?'99999999':$index_3_total->num;
                $hfref_rate = round($index_3['0']->num/$index_3_total->num*100,2);
                $where['title']='3';
                $where['num']=$index_3['0']->num;
                $where['orgname'] = $v->org;
                $where['rate']=$hfref_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'3','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'3','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 4、出院前HFREF患者使用β受体阻滞剂
                $index_4 = DB::select('select count(*) as num from `patient` left join `detection_result` on `patient`.`id` = `detection_result`.`pid` left join `medical_history` on `patient`.`id` = `medical_history`.`pid` left join `therapeutic_drugs` on `patient`.`id` = `therapeutic_drugs`.`pid` where `patient`.`is_del` = 0 and `patient`.`orgid` = ? and `shex_score` < 40 and `medical_history`.`bein_time` like ? and is_beta =1',[$orgid,$mon.'%']);
                $beta_rate = round($index_3['0']->num/$index_3_total->num*100,2);
                $where['title']='4';
                $where['num']=$index_4['0']->num;
                $where['rate']=$beta_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'4','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'4','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 5、出院前有适应症的HFREF患者使用醛固酮受体拮抗剂
                $index_5_total = DB::select(' select count(*) as num from `patient` left join `detection_result` on `patient`.`id` = `detection_result`.`pid` 
                    left join `medical_history` on `patient`.`id` = `medical_history`.`pid` 
                    left join `present_symptom` on `patient`.`id` = `present_symptom`.`pid` 
                    where `patient`.`is_del` = 0 and `patient`.`orgid` = ? and `shex_score` < 35 and `medical_history`.`bein_time` like ? ',[$orgid,$mon.'%']);
                $index_5 = DB::select('select count(*) as num from `patient` left join `detection_result` on `patient`.`id` = `detection_result`.`pid` 
                    left join `medical_history` on `patient`.`id` = `medical_history`.`pid` 
                    left join `present_symptom` on `patient`.`id` = `present_symptom`.`pid` 
                    where `patient`.`is_del` = 0 and `patient`.`orgid` = ? and `shex_score` < 35 and `medical_history`.`bein_time` like ? and congestive_level >=2 and congestive_level <=4',[$orgid,$mon.'%']);
                $index_5_total = empty($index_5_total['0']->num)?'99999999':$index_5_total['0']->num;
                $ALD_rate = round($index_5['0']->num/$index_5_total*100,2);
                $where['title']='5';
                $where['num']=$index_5['0']->num;
                $where['rate']=$beta_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'5','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'5','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 6、有房颤的心衰患者使用抗凝治疗
                $index_6_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['patient.is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first();
                $index_6 = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['patient.is_del'=>'0',['patient.orgid','=',$orgid],'is_heart_cd'=>'1',['medical_history.bein_time','like',$mon.'%']])
                    ->first();
                $index_6_total = empty($index_6_total->num)?'99999999':$index_6_total->num;
                $anticoagulant_rate = round($index_6->num/$index_6_total*100,2);
                $where['title']='6';
                $where['num']=$index_6->num;
                $where['rate']=$anticoagulant_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'6','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'6','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 7、出院后一周电话随访率
                $index_7_total = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0  and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_7 = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0 and f.follow_data = 7 and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_7_total = empty($index_7_total['0']->num)?'99999999':$index_7_total['0']->num;
                $Oneweek_rate = round($index_7['0']->num/$index_7_total*100,2);
                $where['title']='7';
                $where['num']=$index_7['0']->num;
                $where['rate']=$Oneweek_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'7','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'7','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 8、一月随访率
                $index_8_total = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0  and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_8 = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0 and f.follow_data = 1 and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_8_total = empty($index_8_total['0']->num)?'99999999':$index_8_total['0']->num;
                $Oneweek_rate = round($index_8['0']->num/$index_8_total*100,2);
                $where['title']='8';
                $where['num']=$index_8['0']->num;
                $where['rate']=$Oneweek_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'8','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'8','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 9、三月随访率
                $index_9_total = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0  and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_9 = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0 and f.follow_data = 3 and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_9_total = empty($index_9_total['0']->num)?'99999999':$index_9_total['0']->num;
                $Oneweek_rate = round($index_9['0']->num/$index_9_total*100,2);
                $where['title']='9';
                $where['num']=$index_9['0']->num;
                $where['rate']=$Oneweek_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'9','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'9','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }
                // 10、一月随访率
                $index_10_total = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0  and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_10 = DB::select('SELECT count(*) as num from patient p 
                    LEFT JOIN medical_history m on p.id = m.pid 
                    LEFT JOIN follow_status f on p.id = f.pid
                    where p.is_del =0 and f.follow_data = 12 and orgid = ? and m.bein_time like ?',[$orgid,$mon.'%']);
                $index_10_total = empty($index_10_total['0']->num)?'99999999':$index_10_total['0']->num;
                $Oneweek_rate = round($index_10['0']->num/$index_10_total*100,2);
                $where['title']='10';
                $where['num']=$index_10['0']->num;
                $where['rate']=$Oneweek_rate;
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'10','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'10','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 11、门诊HFREF患者使用ACEI/ARB/ARNI
                // 1月，3月，12月数据分别计算，比较
                $index_11_total_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_11_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and (is_acei=1 or is_arni=1 or is_arb=1) and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_11_total_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_11_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and (is_acei=1 or is_arni=1 or is_arb=1) and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_11_total_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_11_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and (is_acei=1 or is_arni=1 or is_arb=1) and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                if(empty($index_11_total_f['0']->num)){
                    $rate1 = 0;
                }else{
                    $rate1 = round($index_11_f['0']->num/ $index_11_total_f['0']->num*100,2);
                }
                if(empty($index_11_total_s['0']->num)){
                    $rate2 = 0;
                }else{
                    $rate2 = round($index_11_s['0']->num/ $index_11_total_s['0']->num*100,2);
                }
                if(empty($index_11_total_y['0']->num)){
                    $rate3 = 0;
                }else{
                    $rate3 = round($index_11_y['0']->num/ $index_11_total_y['0']->num*100,2);
                }
                // 取最大值
                $rate11 = max($rate1,$rate2,$rate3);
                if($rate1 == $rate11){
                    $index_11_num = $index_11_f['0']->num;
                }elseif($rate2 == $rate11){
                    $index_11_num = $index_11_s['0']->num;
                }else{
                    $index_11_num = $index_11_y['0']->num;
                }
                $where['title']='11';
                $where['num']=$index_11_num;
                $where['rate']=$rate11;
                // 同步数据
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'11','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'11','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 12、门诊HFREF患者使用β受体阻滞剂
                // 1月，3月，12月数据分别计算，比较
                $index_12_total_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_12_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and is_bt_szj=1 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_12_total_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_12_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and is_bt_szj=1 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_12_total_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_12_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and is_bt_szj=1  and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                if(empty($index_12_total_f['0']->num)){
                    $rate1 = 0;
                }else{
                    $rate1 = round($index_12_f['0']->num/ $index_12_total_f['0']->num*100,2);
                }
                if(empty($index_12_total_s['0']->num)){
                    $rate2 = 0;
                }else{
                    $rate2 = round($index_12_s['0']->num/ $index_12_total_s['0']->num*100,2);
                }
                if(empty($index_12_total_y['0']->num)){
                    $rate3 = 0;
                }else{
                    $rate3 = round($index_12_y['0']->num/ $index_12_total_y['0']->num*100,2);
                }
                // 取最大值
                $rate12 = max($rate1,$rate2,$rate3);
                if($rate1 == $rate12){
                    $index_12_num = $index_12_f['0']->num;
                }elseif($rate2 == $rate12){
                    $index_12_num = $index_12_s['0']->num;
                }else{
                    $index_12_num = $index_12_y['0']->num;
                }
                $where['title']='12';
                $where['num']=$index_12_num;
                $where['rate']=$rate12;
                // 同步数据
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'12','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'12','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                // 13、门诊HFREF患者使用醛固酮受体拮抗剂
                $index_13_total_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_13_f = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =1 and zssx_score_val<40 and is_del = 0 and is_ald=1 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_13_total_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_13_s = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =3 and zssx_score_val<40 and is_del = 0 and is_ald=1 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_13_total_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                $index_13_y = DB::select('select count(*) as num from patient p LEFT JOIN medical_history m on p.id = m.PID LEFT JOIN follow_status f on p.id = f.pid  where follow_data =12 and zssx_score_val<40 and is_del = 0 and is_ald=1  and orgid=? and bein_time like ?',[$orgid,$mon.'%']);
                if(empty($index_13_total_f['0']->num)){
                    $rate1 = 0;
                }else{
                    $rate1 = round($index_13_f['0']->num/ $index_13_total_f['0']->num*100,2);
                }
                if(empty($index_13_total_s['0']->num)){
                    $rate2 = 0;
                }else{
                    $rate2 = round($index_13_s['0']->num/ $index_13_total_s['0']->num*100,2);
                }
                if(empty($index_13_total_y['0']->num)){
                    $rate3 = 0;
                }else{
                    $rate3 = round($index_13_y['0']->num/ $index_13_total_y['0']->num*100,2);
                }
                // 取最大值
                $rate13 = max($rate1,$rate2,$rate3);
                if($rate1 == $rate13){
                    $index_13_num = $index_13_f['0']->num;
                }elseif($rate2 == $rate13){
                    $index_13_num = $index_13_s['0']->num;
                }else{
                    $index_13_num = $index_13_y['0']->num;
                }
                $where['title']='13';
                $where['num']=$index_13_num;
                $where['rate']=$rate13;
                // 同步数据
                if (QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'13','category'=>'1'])->exists()) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'13','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

               
                // 14、HFREF患者ACEI/ARB/ARNI达标率
                // $index_14_total = DB::select();
                /**
                 * 16.院内死亡率
                 */
                $index_16_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first(); 
                $index_16 = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('therapeutic_drugs','patient.id','therapeutic_drugs.pid')
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0','nitrate_drugs'=>'1','inotropic_action_of_vein'=>'3',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first();
                $death_rate = 0;
                if(!empty($index_16_total->num)){
                    $death_rate = round($index_16->num/$index_16_total->num*100,2);
                }
                $where['title']='16';
                $where['num']=$index_16->num;
                $where['rate']=$death_rate;
                $res = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'16','category'=>'1'])->exists();
                if ($res) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'16','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                /**
                * 17.出院后30天死亡率
                */
                $index_17_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first(); 
                $index_17 = DB::select('SELECT DATEDIFF(f.nitrate_drugs,m.leave_time) as time,m.leave_time,f.nitrate_drugs,p.id  from patient p 
                    LEFT JOIN medical_history m on p.id = m.PID
                    LEFT JOIN follow_status f on p.id = f.pid where follow_data =1 and orgid = ? and bein_time like ?
                    HAVING time BETWEEN 0 AND 30',[$orgid,$mon.'%']
                    );
                $death_rate2 = 0;
                if(!empty($index_17_total->num)&&!empty($index_17['0']->num)){
                    $death_rate2 = round($index_17['0']->num/$index_17_total->num*100,2);
                }
                $where['title']='17';
                $where['num']=empty($index_17['0']->num)?'':$index_17['0']->num;
                $where['rate']=$death_rate2;
                $res = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'17','category'=>'1'])->exists();
                if ($res) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'17','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

                /**
                * 18.30天再入院率
                */
                $index_18_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first(); 
                $index_18 = DB::select('SELECT count(*) as num from (
                    SELECT  DATEDIFF(bein_time,last_hos_time)as time from patient p LEFT JOIN medical_history m on p.id = m.PID   and is_del=0 and  orgid = ? and bein_time like ?
                    HAVING time BETWEEN 1 and 30)aa',[$orgid,$mon.'%']
                );
                $readmission_rate = 0;
                if(!empty($index_18_total->num)){
                    $readmission_rate = round($index_18['0']->num/$index_18_total->num*100,2);
                }
                $where['title']='18';
                $where['num']=$index_18['0']->num;
                $where['rate']=$readmission_rate;
                $res = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'18','category'=>'1'])->exists();
                if($res){
                    $upd = QcCache::where(['title'=>'18','category'=>'1',['orgname','=',$v->org],['month','=',$mon]])->update($where);
                }else{
                    $insert = QcCache::insert($where);
                }
                /**
                 * 19.1年再入院率
                 */
                 $index_19_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first(); 
                $index_19 = DB::select('SELECT count(*) as num from (
                    SELECT  DATEDIFF(bein_time,last_hos_time)as time from patient p LEFT JOIN medical_history m on p.id = m.PID   and is_del=0 and  orgid = ? and bein_time like ?
                    HAVING time BETWEEN 30 and 365)aa',[$orgid,$mon.'%']
                );
                $readmission_rate2 = 0;
                if(!empty($index_19_total->num)){
                    $readmission_rate2 = round($index_19['0']->num/$index_19_total->num*100,2);
                }
                $where['title']='19';
                $where['num']=$index_19['0']->num;
                $where['rate']=$readmission_rate2;
                $res = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'19','category'=>'1'])->exists();
                if($res){
                    $upd = QcCache::where(['title'=>'19','category'=>'1',['orgname','=',$v->org],['month','=',$mon]])->update($where);
                }else{
                    $insert = QcCache::insert($where);
                }
                /**
                * 20、1年死亡率
                */
                 $index_20_total = DB::table('patient')
                    ->select(DB::raw('count(*) as num'))
                    ->leftJoin('medical_history','patient.id','medical_history.pid')
                    ->where(['is_del'=>'0',['patient.orgid','=',$orgid],['medical_history.bein_time','like',$mon.'%']])
                    ->first(); 
                $index_20 = DB::select('SELECT DATEDIFF(f.nitrate_drugs,m.leave_time) as time,m.leave_time,f.nitrate_drugs,p.id  from patient p 
                    LEFT JOIN medical_history m on p.id = m.PID
                    LEFT JOIN follow_status f on p.id = f.pid where follow_data =1 and orgid = ? and bein_time like ?
                    HAVING time BETWEEN 0 AND 30',[$orgid,$mon.'%']
                    );
                $death_rate3 = 0;
                if(!empty($index_17_total->num)&&!empty($index_20['0']->num)){
                    $death_rate3 = round($index_20['0']->num/$index_20_total->num*100,2);
                }
                $where['title']='20';
                $where['num']=empty($index_20['0']->num)?'':$index_20['0']->num;
                $where['rate']=$death_rate3;
                $res = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'20','category'=>'1'])->exists();
                if ($res) {
                    $upd = QcCache::where([['orgname', '=',$v->org],['month', '=',$mon],'title'=>'20','category'=>'1'])->update($where);
                }else{ 
                    $insert = QcCache::insert($where);
                }

        	}
        }
    }
    // 超声心动图
}