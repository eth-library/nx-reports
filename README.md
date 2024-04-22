# NX reports

A [Matomo](https://matomo.org) Plugin for custom reporting. This plugin calculates rollup reports on a custom dimension and enriches the groups with other parameters for each line (e.g. counts of event types)

## Requirements

- Matomo 5.x

## Dependencies from Matomo marketplace

- Custom Dimensions (integrated in Matomo)
- Custom Translations
- (optional) Invalidate reports

## Website setup

### Create custom dimensions

For E-Periodica, create three new custom "Action" dimensions (Admin -> Websites -> Custom Dimensions):

1. prefix
2. title
3. uniqueId

a) Take note of the IDs displayed, they need to be in sync with the E-Periodica client.

b) Take note of the position of the custom dimension in the actions list,
this is the id that needs to be used in the JSON configuration (e.g. position 1, external id 3 => customdimension1 in actions table)

### Copy sample JSON and adjust ids

## Dev setup (only needed for plugin development)

### Matomo dev container setup

```
cd dev
cp .env .env-dev
# adjust values in .env
docker-compose up -d
```

### Matomo base setup

Now open http://localhost:8990/ and follow installation (see sample config file in docker/matomo for values)

When finished, use the following commands to activate the NX Reporting plugin:

```
./console development:enable
./console plugin:activate CustomTranslations NxReporting
```

### NX Reporting configuration

Open `http://localhost:8990/index.php?module=SitesManager&action=index&period=day&idSite=1&activated=` and copy/past
sample config from the `docs` folder.

### Useful commands for testing / debugging

Easier debugging:

- Deactivate the browser reporting in the archive settings (http://localhost:8990/index.php?module=CoreAdminHome&action=generalSettings&idSite=1&period=day&date=today&activated=).
- Set minimum archive time to 1 second (so our commands alway fetch fresh data).

Reproducible report generation for a specific date range:
`docker compose exec matomo /var/www/matomo/console core:archive --force-date-range=2024-04-16,2024-04-17`

will output something like this:

```
INFO      [2024-04-17 14:00:00] 209  Start processing archives for site 1.
INFO      [2024-04-17 14:00:00] 209    Will invalidate archived reports for today in site ID = 1's timezone (2024-04-17 00:00:00).
INFO      [2024-04-17 14:00:01] 209  Archived website id 1, period = day, date = 2024-04-17, segment = '', 1 visits found. Time elapsed: 0.174s
INFO      [2024-04-17 14:00:01] 209  Archived website id 1, period = week, date = 2024-04-15, segment = '', 1 visits found. Time elapsed: 0.169s
INFO      [2024-04-17 14:00:01] 209  Archived website id 1, period = month, date = 2024-04-01, segment = '', 1 visits found. Time elapsed: 0.184s
INFO      [2024-04-17 14:00:01] 209  Archived website id 1, period = year, date = 2024-01-01, segment = '', 1 visits found. Time elapsed: 0.171s
```

Invalidate report segments:
`docker compose exec matomo /var/www/matomo/console core:invalidate-report-data --dates 2024-04-17`

# Notes

- Security: don't use this plugin if any untrusted users have access to the website configuration in your Matomo instance. The plugin currently does not validate any
  configuration parameters, which - if used wrongly or badly - might ultimately lead to data loss or corruption of your entire matomo database.

# License

MIT License - which is compatible with Matomo according to https://matomo.org/licences/

# Credits

This plugin for matomo has been developed for ETH Library by [NEXTENSION GmbH](https://nextension.com).
