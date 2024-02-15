<!DOCTYPE html>
<html lang="en">
<head>
    <title>Alterar Senha FAESA</title>
</head>
<style>
    body{
        font-family:Arial, sans-serif;
        font-size:12px;
        text-align: justify;
    }
</style>
<body>
	<img style="width: 180px; height: 150;" src="http://servicos.faesa.br/imagens/logo_faesa_cor.png" alt="Faesa">
	<br>
    <br>
    <div>Prezado(a), {{ $data['nome'] }}</div>
	
	<p>	Seu login na FAESA é: <b>{{ $data['login'] }}</b></p>
	
	<p>	Conforme solicitado, segue abaixo o link para criação da nova senha ou alteração da senha atual.</p>
	
	<p>{{ $data['link'] }}</p>
	
	<b>ATENÇÃO!</b> Este link é válido por 24 horas, após este período será necessário uma nova solicitação.</br>
	Caso não tenha solicitado a troca de senha, favor entrar em contato no e-mail <b>suporte.aluno@faesa.br</b>.
    <br>
    <br>
    <br>
</body>
</html>