<?php

declare(strict_types=1);

namespace OCA\SouveraCentral\Service;

/**
 * Stalwart MTA-Hook-Vertrag (verifiziert gegen Stalwart-Doku 0.15/0.16
 * und Server-Source crates/smtp/src/inbound/hooks/message.rs):
 *
 * REQUEST (Auszug, relevant für uns):
 *   context.sasl.login      — SMTP-authentifizierter Account
 *   envelope.from.address   — MAIL FROM
 *   message.contents        — die vollständige Roh-Mail (Header+Body)
 *   message.headers         — [[name, value], …]
 *
 * RESPONSE:
 *   {"action": "accept"}                                        — unverändert weiter
 *   {"action": "accept", "modifications": [
 *       {"type": "replaceContents", "value": "<neue Roh-Mail>"}  — Body ersetzt
 *   ]}
 *
 * Fail-open: Fehler werden NIEMALS als reject/discard/quarantine oder
 * 4xx/5xx beantwortet — immer accept ohne Modifications.
 */
final class HookPayload {

	/**
	 * Roh-Mail aus dem Hook-Payload extrahieren — dokumentierter Pfad
	 * message.contents zuerst, danach tolerante Alt-Shapes.
	 */
	public static function parseRawMessage(array $payload): ?string {
		// 1. Dokumentiert: message.contents
		$v = $payload['message']['contents'] ?? null;
		if (\is_string($v) && $v !== '') {
			return $v;
		}
		// 2. Tolerante Alt-Shapes (message als String, raw, data, body)
		foreach (['message', 'raw', 'data', 'body'] as $key) {
			$v = $payload[$key] ?? null;
			if (\is_string($v) && $v !== '') {
				if (!\str_contains($v, "\n") && \base64_decode($v, true) !== false
					&& \preg_match('/^[A-Za-z0-9+\/=]+$/', \substr($v, 0, 256)) === 1) {
					$decoded = \base64_decode($v, true);
					if ($decoded !== false && $decoded !== '') {
						return $decoded;
					}
				}
				return $v;
			}
		}
		// 3. Verschachtelt: data.message / data.body
		if (isset($payload['data']) && \is_array($payload['data'])) {
			foreach (['message', 'raw', 'body', 'contents'] as $key) {
				$v = $payload['data'][$key] ?? null;
				if (\is_string($v) && $v !== '') {
					return $v;
				}
			}
		}
		return null;
	}

	/**
	 * Authentifizierten SMTP-Absender extrahieren — dokumentierte Pfade
	 * (context.sasl.login, envelope.from.address) zuerst, danach tolerante
	 * Alt-Shapes. Liefert eine E-Mail-Adresse oder null.
	 */
	public static function parseSender(array $payload): ?string {
		// 1. Dokumentiert: context.sasl.login (Account-Name, häufig E-Mail)
		$login = $payload['context']['sasl']['login'] ?? null;
		if (\is_string($login) && \str_contains($login, '@')) {
			return self::normalizeAddress($login);
		}
		// 2. Dokumentiert: envelope.from.address
		$from = $payload['envelope']['from']['address'] ?? null;
		if (\is_string($from) && \str_contains($from, '@')) {
			return self::normalizeAddress($from);
		}
		// 3. Tolerante Alt-Shapes
		foreach (['authenticated_as', 'sasl_user', 'authenticated-user', 'auth'] as $key) {
			$v = $payload[$key] ?? null;
			if (\is_string($v) && \str_contains($v, '@')) {
				return self::normalizeAddress($v);
			}
		}
		if (isset($payload['env']) && \is_array($payload['env'])) {
			foreach (['authenticated_as', 'sasl_user'] as $key) {
				$v = $payload['env'][$key] ?? null;
				if (\is_string($v) && \str_contains($v, '@')) {
					return self::normalizeAddress($v);
				}
			}
		}
		return null;
	}

	/**
	 * Antwort-Array im dokumentierten Hook-Vertrag.
	 *
	 * @param string|null $modified null = unverändert weiterleiten,
	 *                              sonst die vollständige neue Roh-Mail
	 * @return array<string, mixed>
	 */
	public static function acceptResponse(?string $modified): array {
		if ($modified === null || $modified === '') {
			return ['action' => 'accept'];
		}
		return [
			'action' => 'accept',
			'modifications' => [
				['type' => 'replaceContents', 'value' => $modified],
			],
		];
	}

	private static function normalizeAddress(string $addr): string {
		$addr = \strtolower(\trim($addr));
		$addr = \ltrim($addr, '<');
		$end = \strpos($addr, '>');
		if ($end !== false) {
			$addr = \substr($addr, 0, $end);
		}
		return \trim($addr);
	}
}
