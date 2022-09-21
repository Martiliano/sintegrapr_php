<?php

namespace Spider\Sintegra;

class Pr {
    protected $status = true;
    protected $error = "";
    protected $errno = 0;
    protected $error_info;

    protected $processId;

    protected $cnpj = "";
    protected $info_cnpj = Array();
    protected $tem_outras_ie = false;
    protected $consultar_prox_id = false;
    protected $sintegra_anterior = "";

    protected $cookieFiles = Array();
    protected $captcha_file = "captcha.jpeg";

    protected $url_sintegra = "";
    protected $url_captcha = "";
    protected $url_consulta = "";

    protected $url_host = "";
    protected $url_origin = "";
    protected $url_referer = "";

    public function __construct() {
        $this->url_sintegra = "http://www.sintegra.fazenda.pr.gov.br/sintegra/";
        $this->url_captcha = "http://www.sintegra.fazenda.pr.gov.br/sintegra/captcha?";
        $this->url_consulta = "http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1/consultar";

        $this->url_host = "www.sintegra.fazenda.pr.gov.br";
        $this->url_origin = "http://www.sintegra.fazenda.pr.gov.br";
        $this->url_referer = "http://www.sintegra.fazenda.pr.gov.br/sintegra/";
    }

    public function __destruct() {
    }

    private function frand($min, $max, $decimals = 0) {
        $scale = pow(10, $decimals);
        return mt_rand($min * $scale, $max * $scale) / $scale;
    }

    private function run($command, $outputFile = '/dev/null') {
        $this->processId = shell_exec(sprintf('%s > %s 2>&1 & echo $!', $command, $outputFile));
    }

    private function validar_cnpj($cnpj){
        $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
    
        if (strlen($cnpj) != 14)
        return false;
    
        if (preg_match('/(\d)\1{13}/', $cnpj))
		return false;
    
        for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++)
        {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
    
        $resto = $soma % 11;
    
        if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto))
		return false;
    
        for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++)
        {
            $soma += $cnpj[$i] * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }

        $resto = $soma % 11;
        return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
    }

    private function formatar_cnpj($value)
    {
        $cnpj = preg_replace("/\D/", '', $value);

        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", $cnpj);
    }

    private function request_form(){

        if($this->errno>0){
            return false;
        }

        $tempfilename = tempnam('.','coockie-');

        array_push($this->cookieFiles, $tempfilename);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url_sintegra);

        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFiles[0]);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_NOBODY, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($curl);

        if (curl_errno($curl) > 0) {
            $this->error = "Curl Error: " . curl_error($curl);
            $this->error_info = curl_getinfo($curl);
            $this->errno = 1;
            $this->status = false;
        }

        curl_close($curl);

        if($this->errno>0){
            return false;
        }

        return true;
    }

    private function request_captcha(){

        if($this->errno>0){
            return false;
        }

        $tempfilename = tempnam('.','coockie-');

        array_push($this->cookieFiles, $tempfilename);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url_captcha . $this->frand(0, 2, 16));

        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieFiles[0]); 
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFiles[1]);

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);

        $headers = ["Host: $this->url_host",
            "Origin: $this->url_origin",
            "Referer: $this->url_referer",
            "Upgrade-Insecure-Requests: 1",
            "Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8",
            "Accept-Encoding: gzip, deflate",
            "Accept-Language: pt-BR,pt;q=0.9,en;q=0.8,ja;q=0.7",
            "Cache-Control: max-age=0",
            "Connection: keep-alive"
            ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);

        if (curl_errno($curl) > 0) {
            $this->error = "Curl Error: " . curl_error($curl);
            $this->error_info = curl_getinfo($curl);
            $this->errno = 2;
            $this->status = false;
        }

        curl_close($curl);

        if($this->errno>0){
            return false;
        }

        $pos=strpos($resp, 'image/jpeg') + 5;

        $img =substr($resp, $pos+strlen('image/jpeg')-1, strlen($resp)-1);

        $fp = fopen($this->captcha_file, 'w');
        fwrite($fp, $img);
        fclose($fp);

        return true;
    }

    private function request_cnpj_info(){

        if($this->errno>0){
            return false;
        }

        $tempfilename = tempnam('.','coockie-');

        array_push($this->cookieFiles, $tempfilename);

        $this->run('display '.$this->captcha_file);
        $captcha = readline('Digite o codigo do Captcha : ');

        $arr = [
            "_method" => "POST",
            "data[Sintegra1][CodImage]" => $captcha,
            "data[Sintegra1][Cnpj]" => $this->cnpj,
            "empresa" => "Consultar Empresa",
            "data[Sintegra1][Cadicms]" => "",
            "data[Sintegra1][CadicmsProdutor]" => "",
            "data[Sintegra1][CnpjCpfProdutor]" => "",
        ];
        $payload = http_build_query($arr);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url_sintegra);

        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieFiles[1]); 
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFiles[2]);

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

        $headers = ["Content-Length: ".strlen($payload),
            "Content-Type: application/x-www-form-urlencoded",
            "Host: $this->url_host",
            "Origin: $this->url_origin",
            "Referer: $this->url_referer",
            "Upgrade-Insecure-Requests: 1",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Language: pt-BR,pt;q=0.9,en;q=0.8,ja;q=0.7",
            "Cache-Control: max-age=0",
            "Connection: keep-alive"
            ];

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);

        if (curl_errno($curl) > 0) {
            $this->error = "Curl Error: " . curl_error($curl);
            $this->error_info = curl_getinfo($curl);
            $this->errno = 3;
            $this->status = false;
        }

        curl_close($curl);

        if($this->errno>0){
            return false;
        }

        $poserr = strpos($response, "Location:");

        if ($poserr === false) {
        } else {
            $this->error = "Erro desconhecido na requisição.";
            $this->error_info = "";
            $this->errno = 5;
            $this->status = false;

            $poserr = strpos($response, "http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1/erro/empresa/image/");

            if ($poserr === false) {
            } else {
                $this->error = "Captcha incorreto.";
                $this->errno = 6;
                return false;
            }

            //Location: http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1/erro/empresa/consulta/99783168185198+-+Inscri%C7%C3o+CNPJ+Inv%C1lida
            $poserr = strpos($response, "Inscri%C7%C3o+CNPJ+Inv%C1lida");

            if ($poserr === false) {
            } else {
                $this->error = "CNPJ Invalido.";
                $this->errno = 7;
                return false;
            }

            // Location: http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1/erro/empresa/consulta/21918100000171+-+CNPJ+N%C3o+Cadastrado+no+Cad.icms+Pr
            $poserr = strpos($response, "CNPJ+N%C3o+Cadastrado+no+Cad.icms+Pr");

            if ($poserr === false) {
            } else {
                $this->error = "CNPJ Não Cadastrado no Parana.";
                $this->errno = 8;
            }

            return false;
        }

        return $this->parse_info_html(true, $response);
    }

    private function request_cnpj_next_info(){

        if(!$this->tem_outras_ie){
            return true;
        }

        if(!$this->consultar_prox_id){
            return true;
        }

        if($this->errno>0){
            return false;
        }

        $cookie_read_idx = count($this->cookieFiles) - 1;

        $tempfilename = tempnam('.','coockie-');

        array_push($this->cookieFiles, $tempfilename);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url_consulta);

        $arr = [
            "_method" => "POST",
            "data[Sintegra1][campoAnterior]" => $this->sintegra_anterior,
            "consultar" => ""
        ];
        $payload = http_build_query($arr);

        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieFiles[$cookie_read_idx]); 
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieFiles[$cookie_read_idx + 1]);

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);

        $headers = ["Content-Length: ".strlen($payload),
            "Content-Type: application/x-www-form-urlencoded",
            "Host: www.sintegra.fazenda.pr.gov.br",
            "Origin: http://www.sintegra.fazenda.pr.gov.br",
            "Referer: http://www.sintegra.fazenda.pr.gov.br/sintegra/",
            "Upgrade-Insecure-Requests: 1",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Language: pt-BR,pt;q=0.9,en;q=0.8,ja;q=0.7",
            "Cache-Control: max-age=0",
            "Connection: keep-alive"
            ];
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);

        if (curl_errno($curl) > 0) {
            $this->error = "Curl Error: " . curl_error($curl);
            $this->error_info = curl_getinfo($curl);
            $this->errno = 4;
            $this->status = false;
        }

        curl_close($curl);

        if($this->errno>0){
            return false;
        }

        return $this->parse_info_html(false, $response);
    }

    private function parse_info_html($first, $resp) {

        $pattern = '/<td\s*([^<]+)>\s*([^<]+)<\/td>/';
        preg_match_all($pattern, $resp, $matches);

        if(count($matches)<3){
            $this->error = "Erro na analise do Html (Cnpj)";
            $this->errno = 999993;
            $this->status = false;
            return false;
        }

        $ignore = false;
        $resposta = Array();
        for($idx = 0;$idx<count($matches[2]);$idx++){

            if($ignore){
                $ignore = false;
                continue;
            }

            if($matches[2][$idx] == 'CNPJ:') {
                if($matches[2][$idx + 1] != 'Inscri&ccedil;&atilde;o Estadual:'){
                    $resposta['cnpj'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['cnpj'] = " ";
                }
            }

            if($matches[2][$idx] == 'Inscri&ccedil;&atilde;o Estadual:') {
                if($matches[2][$idx + 1] != 'Nome Empresarial:'){
                    $resposta['ie'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['ie'] = " ";
                }
            }

            if($matches[2][$idx] == 'Nome Empresarial:') {
                if($matches[2][$idx + 1] != 'Logradouro:'){
                    $resposta['razao_social'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['razao_social'] = " ";
                }
            }

            if($matches[2][$idx] == 'Logradouro:') {
                if($matches[2][$idx + 1] != 'N&uacute;mero:'){
                    $resposta['logradouro'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['logradouro'] = " ";
                }
            }

            if($matches[2][$idx] == 'N&uacute;mero:') {
                if($matches[2][$idx + 1] != 'Complemento:'){
                    $resposta['numero'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['numero'] = " ";
                }
            }

            if($matches[2][$idx] == 'Complemento:') {
                if($matches[2][$idx + 1] != 'Bairro:'){
                    $resposta['complemento'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                }
                else{
                    $resposta['complemento'] = " ";
                }
            }

            if($matches[2][$idx] == 'Bairro:') {
                if($matches[2][$idx + 1] != 'Munic&iacute;pio:'){
                    $resposta['bairro'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['bairro'] = " ";
                }
            }

            if($matches[2][$idx] == 'Munic&iacute;pio:') {
                if($matches[2][$idx + 1] != 'UF:'){
                    $resposta['municipio'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['municipio'] = " ";
                }
            }

            if($matches[2][$idx] == 'UF:') {
                if($matches[2][$idx + 1] != 'CEP:'){
                    $resposta['uf'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['uf'] = " ";
                }
            }

            if($matches[2][$idx] == 'CEP:') {
                if($matches[2][$idx + 1] != 'Telefone:'){
                    $resposta['cep'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['cep'] = " ";
                }
            }

            if($matches[2][$idx] == 'Telefone:') {
                if($matches[2][$idx + 1] != 'E-mail:'){
                    $resposta['telefone'] = utf8_encode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['telefone'] = " ";
                }
            }

            if($matches[2][$idx] == 'E-mail:') {
                if($matches[2][$idx + 1] != 'INFORMA&Ccedil;&Otilde;ES COMPLEMENTARES'){
                    $resposta['email'] = html_entity_decode($matches[2][$idx + 1]);
                    $ignore = true;
                    continue;
                } else {
                    $resposta['email'] = " ";
                }
            }

            if($matches[2][$idx] == 'Atividade Econ&ocirc;mica Principal:') {
                if($matches[2][$idx + 1] != 'In&iacute;cio das Atividades:'){
                    $ativ = explode("-", utf8_encode($matches[2][$idx + 1]));
                    if(count($ativ)>1){
                        $resposta['atividade_principal'] = ['codigo' => trim($ativ[0]), 'descricao' => trim($ativ[1])];
                    } else {
                        $resposta['atividade_principal'] = ['codigo' => '0', 'descricao' =>' '];
                    }
                    $ignore = true;
                    continue;
                } else {
                    $resposta['atividade_principal'] =  ['codigo' => '0', 'descricao' =>' '];
                }
            }

            if($matches[2][$idx] == 'In&iacute;cio das Atividades:') {
                if($matches[2][$idx + 1] != 'Situa&ccedil;&atilde;o Atual:'){
                    $resposta['data_inicio'] = $matches[2][$idx + 1];
                    $ignore = true;
                    continue;
                } else {
                    $resposta['data_inicio'] = " ";
                }
            }

            if($matches[2][$idx] == 'Situa&ccedil;&atilde;o Cadastral:') {
                if($matches[2][$idx + 1] != 'Regime Tribut&aacute;rio:'){
                    $situa = explode("-", utf8_encode($matches[2][$idx + 1]));
                    if(count($ativ)>1){
                        $resposta['situacao_atual'] = trim($situa[0]);
                        $resposta['data_situacao_atual'] = trim(str_replace("DESDE"," ",$situa[1]));
                    } else {
                        $resposta['situacao_atual'] = " ";
                        $resposta['data_situacao_atual'] = " ";
                    }
                    $ignore = true;
                    continue;
                } else {
                    $resposta['telefone'] = " ";
                }
            }
        }

        $pattern = '/<b>\s*([^<]+)<\/td>/';
        preg_match_all($pattern, $resp, $dates);

        if(count($dates)<2){
            $this->error = "Erro na analise do Html (Cnpj)";
            $this->errno = 999994;
            $this->status = false;
            return false;
        } else {
            $dt = explode("-", utf8_encode($dates[1][0]));
            $resposta['data'] = trim($dt[0]);
            $resposta['hora'] = trim($dt[1]);
        }

        array_push($this->info_cnpj, $resposta);

        $pattern = '/value=\s*"(.+?)"/';
        preg_match_all($pattern, $resp, $anterior);

        if(count($anterior)>1){
            $this->sintegra_anterior = $anterior[1][1];
        } else {
            $this->sintegra_anterior = "";
        }

        $pattern = '/id="consultar"/';
        preg_match_all($pattern, $resp, $outras);

        if(!empty($outras[0])){
            if($first){
                $this->tem_outras_ie = true;
            }
            $this->consultar_prox_id = true;
        } else {
            $this->consultar_prox_id = false;
        }

        return true;
    }

    private function clean_work_files(){

        if(file_exists($this->captcha_file)) {
            unlink($this->captcha_file);
        } 

        for($idx = 0; $idx<count($this->cookieFiles);$idx++) {
            if(file_exists($this->cookieFiles[$idx])){
                unlink($this->cookieFiles[$idx]);
            }
        }

        $this->cookieFiles = Array();
    }

    public function get_status(){
        return $this->status;
    }

    public function get_error(){
        return $this->error;
    }

    public function get_errno(){
        return $this->errno;
    }

    public function get_result(){
        return $this->info_cnpj;
    }

    public function searchByCnpj($doc){
        $this->status = true;
        $this->error = "";
        $this->errno = 0;
        $this->info_cnpj = Array();
        $this->cnpj = "";
        $this->tem_outras_ie = false;
        $this->consultar_prox_id = false;
        $this->sintegra_anterior = "";

        if(!isset($doc)){
            $this->status = false;
            $this->error = "Cnpj não informado";
            $this->errno = 999991;

            return $this->status;
        }

        if(!$this->validar_cnpj($doc)){
            $this->status = false;
            $this->error = "Cnpj invalido";
            $this->errno = 999992;

            return $this->status;
        }

        $this->cnpj = $this->formatar_cnpj($doc);

        if(!$this->request_form()){
            $this->cookieFiles = Array();
            return $this->status;
        }

        if(!$this->request_captcha()){
            $this->cookieFiles = Array();
            return $this->status;
        }

        if(!$this->request_cnpj_info()){
            if($this->errno > 5 && $this->errno < 9) {
                $this->clean_work_files();
            }
            return $this->status;
        }
        
        while($this->consultar_prox_id){
            if(!$this->request_cnpj_next_info()){
                break;
            }
        }

        $this->clean_work_files();

        return $this->status;
    }
}

?>