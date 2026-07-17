<?php
declare(strict_types=1);

namespace Yu\SocialLogin\Model\Data;

class SocialProfile
{
    public function __construct(
        private readonly string $provider,
        private readonly string $socialId,
        private readonly ?string $email = null,
        private readonly string $firstname = '',
        private readonly string $lastname = ''
    ) {
    }

    /**
     * Provider code, e.g. "google".
     *
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * User id on the provider's side.
     *
     * @return string
     */
    public function getSocialId(): string
    {
        return $this->socialId;
    }

    /**
     * Email shared by the provider.
     *
     * @return string|null null when the provider did not share one
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * First name from the profile; empty string when not shared.
     *
     * @return string
     */
    public function getFirstname(): string
    {
        return $this->firstname;
    }

    /**
     * Last name from the profile; empty string when not shared.
     *
     * @return string
     */
    public function getLastname(): string
    {
        return $this->lastname;
    }

    /**
     * Session-safe representation, restored later via fromArray().
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'social_id' => $this->socialId,
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
        ];
    }

    /**
     * Restore a profile stored in the session by toArray().
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['provider'] ?? ''),
            (string)($data['social_id'] ?? ''),
            isset($data['email']) && $data['email'] !== '' ? (string)$data['email'] : null,
            (string)($data['firstname'] ?? ''),
            (string)($data['lastname'] ?? '')
        );
    }
}
