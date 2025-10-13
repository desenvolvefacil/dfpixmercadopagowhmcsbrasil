<h2>Modulo Gratuito WHMCS PIX Mercado Pago Brasil</h2>
<p>Este modulo é de fácil instalação e configuração, porém se precisar de Suporte Particular nos chame via Whatsapp +55(17)99162-5512</p>

<p><h2>Video Passo a Passo da Instalação e Configuração</h2></p>
<p>https://youtu.be/cBk2kBGY4wA</p>

<p><h2>1 - Criação da Conta</h2></p>
<p>Abrir Conta no Aplicativo Mercado Pago https://mpago.li/1mYaimc</p>

<p><h3>1.1 - Criar Aplicação</h3></p>
<p>https://www.mercadopago.com.br/settings/account/credentials</p>
<dl>
  <dt>Nome da aplicação</dt>
  <dd>Nome de sua Preferencia. Ex: "WHMCS PIX"</dd>
 
  <dt>Qual tipo de solução de pagamento você vai integrar?</dt>
  <dd>Pagamentos on-line</dd>

<dt>Você está usando uma plataforma de e-commerce?</dt>
<dd>Não</dd>

<dt>Qual produto você está integrando?</dt>
<dd>Checkout Transparente</dd>

<dt>Qual API você está integrando?</dt>
<dd>API Pagamentos</dd>

<dt>Modelo de integração</dt>
<dd>Nâo precisa selecionar nenhuma opção</dd>

<p><h3>1.2 - Ativar Credenciais</h3></p>

<dt>Setor</dt>
<dd>Serviços de TI</dd>

<dt>Site (obrigatório)</dt>
<dd>Digite a url de seu site completa como exemplo: https://desenvolvefacil.com.br</dd>

<dt>Access Token</dt>
<dd>Copie e Salve o Valor gerado no Acess Token para utilizar no Modulo</dd>

</dl>


<p><h2>2 - Instalação do Modulo</h2></p>
<ol>
 <li>Faça download e descompacte dentro da pasta Raiz (geralmente public_html ou www) de seu Whmcs.</li>
 <li>
  Ao final da instalação, os arquivos do módulo devem estar na seguinte estrutura no WHMCS:

<pre>
includes/hooks/
  |- dfpixmercadopago-hook.php

modules/gateways/callback
  |- dfpixmercadopago-callback.php

modules/gateways/dfpixmercadopago/
  |- dfpixmercadopago/.htaccess
  |- dfpixmercadopago/verqrcode.php
  |- whmcs.json
  |- logo.png
  |- logopix.png
  
modules/gateways/
  |- dfpixmercadopago.php
</pre>

   
 </li>
 <li>Entre em: Portais de Pagamento e ative o modulo <b>Dfpixmercadopago</b>.</li>
</ol>

<p><h2>3 - Configuração do Modulo</h2></p>
<ol>
 <li><b>Acces Token:</b> Seu Access Token de Produção</li>
</ol>



<p><h2>4 - Enviar QrCode por Email</h2></p>
<p>Basta colcar o comando abaixo nos modelos de email para que o QrCode Pix seja enviado<br />
</p>
<code>{$invoice_payment_link}</code>

<p/>
<h3>Sugestões, Dúvidas?</h3>
<p>Entrem em contato em: <a href="https://desenvolvefacil.com.br">https://desenvolvefacil.com.br</a></p>
<p/>
