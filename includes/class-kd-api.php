<?php

class KD_API {

    const BASE = 'https://dashboard-api-176591343005.europe-west1.run.app/partner-api/v1';

    private string $token;

    public function __construct( string $token ) {
        $this->token = $token;
    }

    private function get( string $endpoint ): array|WP_Error {
        $url      = self::BASE . $endpoint;
        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            return new WP_Error( 'kd_api_error', "HTTP $code: $body" );
        }

        return $data ?? [];
    }

    public function me(): array|WP_Error {
        return $this->get( '/me' );
    }

    public function summary( string $from, string $to ): array|WP_Error {
        return $this->get( "/summary?from={$from}&to={$to}" );
    }

    public function transactions( string $updated_since = '', string $from = '', string $to = '', int $per_page = 500 ): array|WP_Error {
        if ( $updated_since ) {
            $qs = "?updated_since=" . urlencode( $updated_since ) . "&per_page={$per_page}";
        } else {
            $qs = "?from={$from}&to={$to}&per_page={$per_page}";
        }
        return $this->get( "/transactions{$qs}" );
    }

    public function clicks_daily( string $from, string $to ): array|WP_Error {
        return $this->get( "/clicks/daily?from={$from}&to={$to}" );
    }
}
