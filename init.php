<?php
class Discord_Webhook extends Plugin {
	private $host;
	private $discord_api_url = "https://discordapp.com/api/webhooks/";
	private $default_content_length = 0;

	function about() {
		return array(1.0,
			"Filter action to send articles to Discord",
			"Roliga");
	}

	function init($host) {
		$this->host = $host;

		$this->pdo = Db::pdo();

		$host->add_hook($host::HOOK_ARTICLE_FILTER_ACTION, $this);
		$host->add_hook($host::HOOK_FILTER_TRIGGERED, $this);
		$host->add_hook($host::HOOK_PREFS_TAB, $this);

		$host->add_filter_action($this, "action_discord", "Send to Discord");
	}

	function flags() {
		return array("needs_curl" => true);
	}

	function hook_filter_triggered($feed, $owner_uid, $article, $matched_filters, $matched_rules, &$article_filters) {
		# Don't apply to articles that have been deleted by a previous filter
		$delete = false;
		foreach($article_filters as $action_key => $action) {
			if ($action["type"] === "filter") {
				$delete = true;
			}
			if ($delete && $action["param"] === "Discord_Webhook:action_discord") {
				unset($article_filters[$action_key]);
			}
		}
	}

	function validate_webhook_url($webhook_url) {
		return (filter_var($webhook_url, FILTER_VALIDATE_URL)
			&& strpos($webhook_url, $this->discord_api_url) === 0);
	}

	function hook_article_filter_action($article, $action) {
		if ($action == "action_discord") {
			if (!function_exists("curl_init"))
				return $article;

			# Ignore articles that are already in the database so we only trigger the webhook once
			$csth = $this->pdo->prepare("SELECT id FROM ttrss_entries
				WHERE guid = ? OR guid = ?");
			$csth->execute([$article['guid'], $article['guid_hashed']]);
			if ($row = $csth->fetch()) {
				Debug::log("Article already in database, not triggering webhook..", Debug::$LOG_VERBOSE);
				return $article;
			}

			$webhook_url = $this->host->get($this, 'webhook_url');
			if (!$this->validate_webhook_url($webhook_url)) {
				Debug::log("Invalid Discord webhook URL, not triggering webhook..", Debug::$LOG_VERBOSE);
				return $article;
			}

			$payload = array();
			$payload["content"] = $article["title"] . " " . $article["link"];

			$content_length = $this->host->get($this, 'content_length');
			if (!is_numeric($content_length))
				$content_length = $this->default_content_length;

			if ($content_length > 0) {
				$content_stripped = preg_replace('#<br\s*/?>#i', "\n", $article["content"]);
				$content_stripped = strip_tags($content_stripped);
				$content_stripped = trim($content_stripped);

				if (strlen($content_stripped) > $content_length) {
					$payload["content"] .= "\n_" . substr($content_stripped, 0, $content_length) . "..._";
				} else {
					$payload["content"] .= "\n_" . $content_stripped . "_";
				}
			}

			$sth = $this->pdo->prepare("SELECT title FROM ttrss_feeds WHERE id = ?");
			$sth->execute([$article["feed"]["id"]]);

			if ($row = $sth->fetch()) {
				$payload["username"] = $row["title"];
			}

			$ch = curl_init($webhook_url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);

			curl_exec($ch);
			curl_close($ch);
		}

		return $article;
	}

	function hook_prefs_tab($args)
	{
		if ($args != "prefPrefs") return;

		$replacements = array(
			'{webhook_url}' => $this->host->get($this, 'webhook_url'),
			'{content_length}' => $this->host->get($this, 'content_length'),
			'{default_content_length}' => $this->default_content_length,
			'{discord_api_url}' => $this->discord_api_url
		);

		$template = file_get_contents(__DIR__."/pref_template.html");
		$template = str_replace(array_keys($replacements), array_values($replacements), $template);
		print $template;
	}

	function save()
	{
		$this->host->set($this, 'webhook_url', $_POST['webhook_url']);
		$this->host->set($this, 'content_length', $_POST['content_length']);
		echo __("Configuration saved");
	}

	function api_version() {
		return 2;
	}

}
?>
