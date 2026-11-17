<?php

declare(strict_types=1);

namespace App\Infra\Email;

use App\Infra\Config;
use PHPMailer\PHPMailer\PHPMailer;

/** Envio via SMTP (PHPMailer). */
final class PhpMailerEnviador implements EmailEnviador
{
    public function __construct(private readonly Config $config)
    {
    }

    public function enviar(string $para, string $assunto, string $corpo): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->config->get('SMTP_HOST', 'localhost') ?? 'localhost';
        $mail->Port = $this->config->int('SMTP_PORT', 587);
        $usuario = $this->config->get('SMTP_USER', '') ?? '';
        $mail->SMTPAuth = $usuario !== '';
        $mail->Username = $usuario;
        $mail->Password = $this->config->get('SMTP_PASS', '') ?? '';
        $seguranca = $this->config->get('SMTP_SECURE', '') ?? '';
        $mail->SMTPSecure = $seguranca;
        $mail->SMTPAutoTLS = $seguranca !== '';
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $this->config->get('SMTP_FROM', 'nao-responder@localhost') ?? 'nao-responder@localhost',
            $this->config->get('SMTP_FROM_NAME', 'Diário de Classe') ?? 'Diário de Classe'
        );
        $mail->addAddress($para);
        $mail->Subject = $assunto;
        $mail->Body = $corpo;
        $mail->isHTML(false);
        $mail->send();
    }
}
