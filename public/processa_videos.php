<?php

    $bloqueia_videos = false;
    $files_dir = '/var/www/html/videos';
    if( !(is_dir($files_dir) && is_writable($files_dir)) ) 
        if(!mkdir($files_dir, 0777, true)) die('Erro de permissão no diretório: '.$files_dir);

    $disk_limit = 6 * pow(2,30); //30GB
    $have_disk_space = (disk_free_space($files_dir)>$disk_limit);
    $site_url = "https://pt.chaturbate.com";
    $espectadores = 0;    

    reiniciaChaves();

    crawl_page_with_dom($site_url);
    //crawl_page_with_file_contents($site_url);

    function crawl_page_with_dom($url, $depth = 5)
    {
        static $seen = array();
        if (isset($seen[$url]) || $depth === 0) {
            return;
        }

        $seen[$url] = true;

        $dom = new DOMDocument('1.0');
        @$dom->loadHTMLFile($url);

        $anchors = $dom->getElementsByTagName('li');
        foreach ($anchors as $key=>$element) {
            if($element->getAttribute('class')=='cams'){
                $espectadores_bruto = explode(',',$element->nodeValue);
                $espectadores_filtrado_01 = array_values(preg_grep('/espetadores/i', $espectadores_bruto))[0];
                $espectadores_filtrado_02 = explode(' ',trim($espectadores_filtrado_01))[0];
                
                $nome_modelo_bruto = $element->parentNode->parentNode->getElementsByTagName('a')[0]->nodeValue;
                $nome_modelo_filtrado = trim($nome_modelo_bruto);

                if($espectadores_filtrado_02>$GLOBALS["espectadores"])
                    $GLOBALS["espectadores"]=$espectadores_filtrado_02;                                  
                
                if($espectadores_filtrado_02>($GLOBALS["espectadores"]/5)){
                    $chave_normalizada = str_pad($key , 5 , '0' , STR_PAD_LEFT);
                    gerenciar_videos($nome_modelo_filtrado,$key);
                    echo $chave_normalizada.' - '.$nome_modelo_filtrado.' - '.$espectadores_filtrado_02,PHP_EOL;
                }                        
            }
        }
    }

    function crawl_page_with_file_contents($site_url){
        $content = file_get_contents($site_url);
        preg_match_all('/\<\w[^<>]*?\>([^<>]+?\<\/\w+?\>)?|\<\/\w+?\>/i', $content, $html );

        foreach($html[0] as $key=>$row){
            if ( (strpos($row, 'data-room')!==false) && (strpos($row, '"> ')!==false) ){
                $segments = explode('/', $row);
                $chave_normalizada = str_pad($key , 5 , '0' , STR_PAD_LEFT);
                echo '<h3>'.$chave_normalizada.' - '.$segments[1].'</h3>';
    
                gerenciar_videos($segments[1],$key);
            }
        }
    }

    function gerenciar_videos($nome_modelo,$key){
        if(naoEncontra($nome_modelo.'.ts')){
            if(naoEncontra($nome_modelo.'.mp4')){
                createTsFile($nome_modelo);
                createMp4File($nome_modelo);
            } else echo '<h3>Aviso: '.$nome_modelo.' já foi processado</h3>',PHP_EOL;
        } else {
            if(naoEncontra($nome_modelo.'.mp4')){
                createMp4File($nome_modelo);
            } else apaga($nome_modelo.'.ts');
        }

        if(encontra($nome_modelo.'.mp4')) incluiChave($key,$nome_modelo.'.mp4');
    }

    function createTsFile($username){
        if($GLOBALS["bloqueia_videos"]) { echo '<h3>Aviso: Gravação desativada.</h3>',PHP_EOL; return; }
        if(!$GLOBALS["have_disk_space"]) { echo '<h3>Aviso: Disco Cheio.</h3>',PHP_EOL; return; }

        echo '<h3>Processing ts file  '.$username.'...</h3>',PHP_EOL;
        $output = shell_exec('streamlink '.$GLOBALS["site_url"].'/'.$username.' worst -o '.$GLOBALS["files_dir"].'/'.$username.'.ts -f --hls-duration 00:'.rand(20, 50));
        echo '<pre>'.$output.'</pre>';
    }
    function createMp4File($username){
        if($GLOBALS["bloqueia_videos"]) { echo '<h3>Aviso: Gravação desativada.</h3>',PHP_EOL; return; }
        if(!$GLOBALS["have_disk_space"]) { echo '<h3>Aviso: Disco Cheio.</h3>',PHP_EOL; return; }

        if (file_exists($GLOBALS["files_dir"].'/'.$username.'.ts')){
                echo '<h3>Processing mp4 file  '.$username.'...</h3>';
                $output = shell_exec('ffmpeg -i '.$GLOBALS["files_dir"].'/'.$username.'.ts -c:v libx264 -c:a aac '.$GLOBALS["files_dir"].'/'.$username.'.mp4');
                echo '<pre>'.$output.'</pre>';
        }
        apaga($username.'.ts');
    }
    function naoEncontra($texto){
        return empty(busca($texto));
    }
    function encontra($texto){
        return !empty(busca($texto));
    }

    function apaga($texto){
        $retorno = busca($texto);
        if(!empty($retorno)) unlink($GLOBALS["files_dir"].'/'.$retorno);
    }
    function busca($texto){
        $files = scandir($GLOBALS["files_dir"]);
        $search = preg_grep('/'.$texto.'/i', $files);
        if(is_array($search) && !empty($search)) return array_values($search)[0];
        return null;
    }
    function incluiChave($key, $texto){
        $files = scandir($GLOBALS["files_dir"]);

        echo '<h3>Verificando nome '.$texto.', chave '.$key.'</h3>';
        $retorno = busca($texto);
        if(in_array($retorno,$files)) {
                echo '<h3>Nome encontrado '.$retorno.'</h3>';
                rename($GLOBALS["files_dir"].'/'.$retorno,$GLOBALS["files_dir"].'/'.str_pad($key , 5 , '0' , STR_PAD_LEFT).'-'.$texto);
        }else echo '<h3>Nome Não encontrado '.$retorno.'</h3>',PHP_EOL; 

    }
    function reiniciaChaves(){
        $files = scandir($GLOBALS["files_dir"],1);
        foreach($files as $file){
                if(substr($file,5,1)=='-' && substr($file,-3)=='mp4') {
                        rename($GLOBALS["files_dir"].'/'.$file,$GLOBALS["files_dir"].'/99999-'.substr($file,6));
                } elseif(substr($file,-3)=='mp4')
                        rename($GLOBALS["files_dir"].'/'.$file,$GLOBALS["files_dir"].'/99999-'.$file);
        }
    }

?>

    