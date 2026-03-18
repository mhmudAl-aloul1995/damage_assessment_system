<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    // Define public properties so they are automatically available in the view
    public $username;
    public $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Damage Assessment System',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.welcome', // Make sure this matches your file in resources/views/emails/welcome.blade.php
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

?>