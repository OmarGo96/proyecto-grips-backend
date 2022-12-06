<style type="text/css">
    @import url("https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;700;900&display=swap");
    :root {
        --principal-color: #f49101;
        --secondary-color: #04487E;
    }
    * {
        margin: 0;
        padding: 0;
        -webkit-box-sizing: border-box;
        box-sizing: border-box;
    }
    body {
        background-color: #ADADAD;
        font-family: 'Roboto',sans-serif;
        text-align: center;
        max-width: 750px;
        padding: 2%;
        margin: 0 auto;
        color: rgb(73, 73, 73);
    }
    header {
        text-align: center;
        display: flex;
        flex-direction: row;
        padding: 2rem;
    }
    .header-content {
        flex: 1;
        align-self: flex-end;
    }

    .logo {
        width: 150px;
    }
    h2 {
        font-size: 20px;
        text-align: left;
        color: var(--principal-color);
        margin-bottom: 20px;
    }
    h3 {
        font-size: 20px;
        color: var(--principal-color);
        margin-bottom: 20px;

    }
    .contenido {
        /*width: 89%;*/
        /*padding: 30px;*/
        background-color: #fff;
        margin: 0 auto;
    }

    .textbody {
        width: 90%;
        margin: 0 auto;
        text-align: center;
        font-size: 13px;
    }

    .textbody p {
        line-height: 1.5;
    }

    .footer2 {
        height: 70px;
        background-color: var(--secondary-color);
        /*width: 89%;*/
        margin: 0 auto;
        color: #fff;
        padding: 15px;
        font-size: 11px;
    }

    .footer2 p {
        text-align: center;
    }

    body a {
        color: var(--principal-color);
    }

    .greenText{
        color: var(--principal-color);
    }


    .advice{
        margin-top: 30px;
        font-size: 15px;
    }

    .note{
        font-size: 9px;
        line-height: 13px;
        text-align: justify;
        margin-bottom: 20px;
    }


    footer{
        margin-top: 20px;
    }
    p {
        text-align: justify;
        line-height: 1.5;
    }
</style>
<div class="contenido">
    <header>
        <div class="header-content" style="flex: 0">
            <img class="logo" src="{{ config('app.base_domain') }}/web/binary/company_logo" alt="{{ config('app.name') }} Logo"/>
        </div>
        <div class="header-content">
            <p style="margin: 0; padding: 0; text-align: end; font-size: 12px; font-weight: bold;">{{$date_reg}}</p>
        </div>
    </header>
    <div class="textbody">
        <h2>Correo para activación de tu cuenta</h2>
        <p>
            Para activar su cuenta debe proporcionar el siguiente código y agregarlo en el apartado de: Código de verificación en la aplicación.
        </p>
        <br>
        <br>
        <p>
            <b>{{ $token }}</b>
        </p>
        <br>
        <br>
        <p style="line-height: normal;">
            Gracias.
            <br><br><br><br>
            Equipo de GripU+00b4s<br><br>
            <span class="greenText">GripU+00b4s App remitente automático</span><br><br><br><br>
        </p>
        <p class="note">Este es un mensaje electrónico. Este mensaje le ha sido enviado a usted como usuario o ha sido referido por un usuario de soporte existente.</p>
    </div>
    <footer>
        <div class="footer2">
        </div>
    </footer>
</div>
