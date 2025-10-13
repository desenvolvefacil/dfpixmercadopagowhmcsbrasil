<h2>Modulo Gratuito WHMCS PIX Mercado Pago Brasil</h2>
<p>Este modulo é de fácil instalação e configuração, porém se precisar de Suporte Particular nos chame via Whatsapp +55(17)99162-5512</p>
<p><h2>Criação da Conta</h2></p>
<p>Abrir Conta no Aplicativo Mercado Pago https://mpago.li/1mYaimc</p>

<p><h2>Video Passo a Passo da Instalação e Configuração</h2></p>
<p>https://youtu.be/cBk2kBGY4wA</p>

<p><h2>Instalação do Modulo</h2></p>
<ol>
 <li>Faça download e descompacte dentro da pasta Raiz (geralmente public_html ou www) de seu Whmcs.</li>
 <li>
  Ao final da instalação, os arquivos do módulo devem estar na seguinte estrutura no WHMCS:
<pre>
includes/hooks/
 |- dfpixmercadopago-hook.php
modules/gateways/
 |- callback/dfpixmercadopago-callback.php
 |- dfpixmercadopago/
 |- dfpixmercadopago/.htaccess
 |- dfpixmercadopago/verqrcode.php
 |- dfpixmercadopago.php
</pre>
 </li>
 <li>Entre em: Portais de Pagamento e ative o modulo <b>Dfpixmercadopago</b>.</li>
</ol>

<p><h2>Configuração do Modulo</h2></p>
<ol>
 <li><b>Acces Token:</b> Seu Access Token de Produção</li>
</ol>



<p><h2>Enviar QrCode por Email</h2></p>
<p>Basta colcar o comando abaixo nos modelos de email para que o QrCode Pix seja enviado<br />
</p>
<code>{$invoice_payment_link}</code>

<p/>
<h3>Sugestões, Dúvidas?</h3>
<p>Entrem em contato em: <a href="https://desenvolvefacil.com.br">https://desenvolvefacil.com.br</a></p>
<p/>
