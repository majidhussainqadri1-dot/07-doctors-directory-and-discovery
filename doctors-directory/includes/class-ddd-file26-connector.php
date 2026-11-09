<?php
/**
 * File07 native verified doctor directory -> File26 derivative search contract.
 *
 * PROPOSED ONLY. Identity, verification and public professional truth stay
 * with File00/File03/File09/File07. No patient, phone, email, fee or direct
 * clinical-case data leaves this adapter.
 */
defined('ABSPATH') || exit;
final class DDD_File26_Connector {
    const SLUG='file07-verified-doctors';
    public static function register(){
        add_filter('sabri_file26_connector_manifests',array(__CLASS__,'append'),20);
        add_action('sabri_file26_connectors_ready',array(__CLASS__,'ready'),20);
        if(function_exists('sabri_file26_register_connector')){sabri_file26_register_connector(self::manifest());}
    }
    public static function manifest(){
        return array(
            'slug'=>self::SLUG,'owner_file'=>'File 07','contract_version'=>'1.0',
            'entity_types'=>array('doctor_directory_projection'),
            'privacy_classes'=>array('public'),'visibility_fields'=>array('public'),
            'deletion_semantics'=>'owner_verification_revocation_gate_pending',
            'status'=>'proposed',
            'event_contract'=>'DoctorDirectoryEligibilityChanged.v1.pending-file26-reconcile',
            'index_schema'=>'sabri.file26.document.v1.1',
            'list_batch'=>array(__CLASS__,'list_batch'),
            'can_view'=>array(__CLASS__,'can_view'),
            'health'=>array(__CLASS__,'health'),
            'fetch_object'=>array(__CLASS__,'fetch_object')
        );
    }
    public static function append($manifests){
        $manifests=is_array($manifests)?$manifests:array();
        foreach($manifests as $manifest){
            if(is_array($manifest)&&($manifest['slug']??'')===self::SLUG){return $manifests;}
        }
        $manifests[]=self::manifest();return $manifests;
    }
    public static function ready($registry){
        if(is_object($registry)&&method_exists($registry,'register')){$registry->register(self::manifest());}
    }
    private static function source_ready(){
        if(!class_exists('DDD_Contracts')||!class_exists('DDD_Repository')||!class_exists('DDD_Observability')
            ||DDD_Observability::safe_mode()||!defined('DDD_DB_VERSION')||!defined('DDD_PROJECTION_SCHEMA')){return false;}
        $deps=DDD_Contracts::dependency_health();
        if(!is_array($deps)||empty($deps['ready'])){return false;}
        if(version_compare((string)get_option('ddd_db_version','0'),DDD_DB_VERSION,'<')){return false;}
        if((int)get_option('ddd_projection_schema',0)!==(int)DDD_PROJECTION_SCHEMA){return false;}
        return true;
    }
    private static function project($public_id,$known_version=0){
        if(!self::source_ready()||!is_string($public_id)||!DDD_Helpers::valid_public_id($public_id)){return null;}
        $doctor=DDD_Repository::resolve_doctor_id($public_id,true);
        if(!$doctor){return null;}
        $live=DDD_Repository::get_live_status($doctor);
        if(empty($live['eligible'])||!empty($live['projection_stale'])){return null;}
        $dto=DDD_Repository::get_by_public_id($public_id);
        if(!is_array($dto)||empty($dto['public_id'])||empty($dto['display_name'])||empty($dto['public_directory_url'])){return null;}
        $version=(int)($live['version']??0);
        if($version<1||($known_version>0&&$version!==$known_version)){return null;}
        $url=DDD_Helpers::same_origin_url((string)$dto['public_directory_url']);
        if(!$url){return null;}
        $summary=trim((string)($dto['professional_title']??'').' '.(string)($dto['specialty']??'').' '.(string)($dto['city']??''));
        return array(
            'connector_slug'=>self::SLUG,'domain'=>'doctor_directory',
            'object_id'=>strtolower((string)$dto['public_id']),
            'object_version'=>$version,'source_event_sequence'=>$version,
            'entity_type'=>'doctor_directory_projection',
            'locale'=>'en-US','state'=>'published','visibility'=>'public',
            'title'=>sanitize_text_field((string)$dto['display_name']),
            'excerpt'=>sanitize_text_field($summary),
            'search_text'=>sanitize_text_field($summary),
            'canonical_url'=>$url,
            'safety_class'=>'verified_professional_directory'
        );
    }
    /** Bounded native doctor projection pagination; exact public ID sourced from File03. */
    public static function list_batch($cursor='',$limit=50,$scope=array()){
        global $wpdb;
        if(!self::source_ready()){return new WP_Error('file07_search_dependencies_unverified','File00/File03/File09 or File07 schema is unavailable.');}
        if(!is_array($scope)||isset($scope['object_id'])){return new WP_Error('file07_search_partial_scope','Full owner snapshot required.');}
        if($cursor!==''&&(!is_string($cursor)||!preg_match('/^[0-9]{1,18}$/D',$cursor))){
            return new WP_Error('file07_search_invalid_cursor','Invalid owner cursor.');
        }
        $table=DDD_Repository::table('projection');
        $limit=max(1,min(50,(int)$limit));$current=(int)$cursor;
        $wpdb->last_error='';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT doctor_id,public_id,version FROM $table WHERE doctor_id>%d ORDER BY doctor_id ASC LIMIT %d",$current,$limit+1),ARRAY_A);
        if(!is_array($rows)||''!==(string)$wpdb->last_error){return new WP_Error('file07_search_source_unreadable','Public directory source could not be scanned.');}
        $more=count($rows)>$limit;if($more){array_pop($rows);}
        $items=array();
        foreach($rows as $row){
            $doc=self::project((string)$row['public_id'],(int)$row['version']);
            if($doc){$items[]=$doc;}
            $current=max($current,(int)$row['doctor_id']);
        }
        return array('items'=>$items,'done'=>!$more,'next_cursor'=>$more?(string)$current:'');
    }
    public static function fetch_object($entity_type,$public_id=''){
        if($entity_type!=='doctor_directory_projection'||!is_string($public_id)){return null;}
        return self::project($public_id);
    }
    /** Live identity, licence, verification and eligibility are re-evaluated for every hit. */
    public static function can_view($document,$audience=array()){
        if(!is_array($document)||($document['connector_slug']??'')!==self::SLUG||
            ($document['visibility']??'')!=='public'||($document['entity_type']??'')!=='doctor_directory_projection'){return false;}
        $latest=self::fetch_object('doctor_directory_projection',(string)($document['object_id']??''));
        return is_array($latest)&&(int)$latest['object_version']===(int)($document['object_version']??0);
    }
    public static function health(){
        if(!self::source_ready()){
            return array('state'=>'degraded','owner_file'=>'File 07','reason'=>'identity_profile_verification_or_directory_contract_unavailable');
        }
        return array('state'=>'healthy','owner_file'=>'File 07',
            'status'=>'proposed','identity_recheck'=>true,'search_revocation_events_approved'=>false);
    }
}
