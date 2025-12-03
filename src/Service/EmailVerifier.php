<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerifier
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Envoie le code de vérification à 6 chiffres.
     */
    public function sendVerificationCode(string $toEmail, string $userName, string $code): void
    {
        $email = (new Email())
            ->from('maharojeancarolinmananga@gmail.com') // Assurez-vous que cette adresse est correcte
            ->to($toEmail)
            ->subject('Votre code de vérification pour CaroStream')
            ->html(
                "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <h2 style='color:#333;'>Bonjour $userName 👋</h2>
                    <p>Merci pour votre inscription sur <strong>EventPlanner</strong>.</p>
                    <p>Veuillez utiliser le code ci-dessous pour vérifier votre compte :</p>
                    <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; color: #007bff; letter-spacing: 5px;'>$code</span>
                    </div>
                    <p style='color: #666;'>Ce code expire dans 10 minutes.</p>
                    <p>Entrez-le dans l'application pour accéder à votre tableau de bord.</p>
                    <hr style='border: 0; border-top: 1px solid #eee;'>
                    <p style='font-size: 12px; color: #aaa;'>Si vous n'avez pas demandé cette vérification, veuillez ignorer cet e-mail.</p>
                </div>
            "
            );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
             // Loggez l'erreur de transport si nécessaire
             error_log('Erreur lors de l\'envoi du code de vérification: ' . $e->getMessage());
             // L'utilisateur recevra 201 mais l'email pourrait échouer. Cela nécessite une gestion côté application.
        }
    }
    
    public function sendInvitationEmail(string $toEmail, string $guestName, string $eventTitle, string $confirmationLink): void
{
    $email = (new Email())
        ->from('maharojeancarolinmananga@gmail.com') // ton email
        ->to($toEmail)
        ->subject("Vous êtes invité à l'événement : $eventTitle")
        ->html("
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                <h2 style='color:#333;'>Bonjour $guestName 👋</h2>
                <p>Vous avez été invité à l'événement <strong>$eventTitle</strong>.</p>
                <p>Pour confirmer votre participation, cliquez sur le lien ci-dessous :</p>
                <div style='margin: 20px 0; text-align:center;'>
                    <a href='$confirmationLink' style='padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:5px;'>Confirmer l'invitation</a>
                </div>
                <p>Si vous n'avez pas demandé cette invitation, vous pouvez ignorer cet email.</p>
                <hr style='border: 0; border-top: 1px solid #eee;'>
                <p style='font-size: 12px; color: #aaa;'>EventPlanner</p>
            </div>
        ");

    try {
        $this->mailer->send($email);
    } catch (TransportExceptionInterface $e) {
        error_log('Erreur lors de l\'envoi de l\'invitation: ' . $e->getMessage());
    }
}

    // L'ancienne méthode sendEmailConfirmation est retirée si vous utilisez uniquement l'OTP.
}