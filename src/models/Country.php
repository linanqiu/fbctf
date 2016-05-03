<?hh // strict

class Country extends Model {
  private function __construct(
    private int $id,
    private string $iso_code,
    private string $name,
    private int $used,
    private int $enabled,
    private string $d,
    private string $transform,
  ) {
  }

  public function getId(): int {
    return $this->id;
  }

  public function getIsoCode(): string {
    return $this->iso_code;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getUsed(): bool {
    return $this->used === 1;
  }

  public function getEnabled(): bool {
    return $this->enabled === 1;
  }

  public function getD(): string {
    return $this->d;
  }

  public function getTransform(): string {
    return $this->transform;
  }

  // Make sure all the countries used field is good
  public static async function genUsedAdjust(
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET used = 1 WHERE id IN (SELECT entity_id FROM levels)',
    );
    await $db->queryf(
      'UPDATE countries SET used = 0 WHERE id NOT IN (SELECT entity_id FROM levels)',
    );
  }

  // Enable or disable a country
  public static async function genSetStatus(
    int $country_id,
    bool $status,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET enabled = %d WHERE id = %d',
      $status ? 1 : 0,
      $country_id,
    );
  }

  // Check if a country is enabled
  public static async function genIsEnabled(
    int $country_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT enabled FROM countries WHERE id = %d LIMIT 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return (intval($result->mapRows()[0]) === 1);
  }

  // Set the used flag for a country
  public static async function genSetUsed(
    int $country_id,
    bool $status,
  ): Awaitable<void> {
    $db = await self::genDb();
    await $db->queryf(
      'UPDATE countries SET used = %d WHERE id = %d LIMIT 1',
      $status ? 1 : 0,
      $country_id,
    );
  }

  // Check if a country is used
  public static async function genIsUsed(
    int $country_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT used FROM countries WHERE id = %d LIMIT 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval($result->mapRows()[0]) === 1;
  }

  // Get all countries
  public static async function genAllCountries(
    bool $map,
  ): Awaitable<array<Country>> {
    $db = await self::genDb();

    if ($map) {
      $result = await $db->queryf('SELECT * FROM countries ORDER BY CHAR_LENGTH(d)');
    } else {
      $result = await $db->queryf('SELECT * FROM countries ORDER BY name');
    }

    $countries = array();
    foreach ($result->mapRows() as $row) {
      $countries[] = self::countryFromRow($row);
    }

    return $countries;
  }

  // All enabled countries. The weird sorting is because SVG lack of z-index
  // and things looking like shit in the map. See issue #20.
  public static async function genAllEnabledCountries(
    bool $map,
  ): Awaitable<array<Country>> {
    $db = await self::genDb();

    if ($map) {
      $result = await $db->queryf('SELECT * FROM countries WHERE enabled = 1 ORDER BY CHAR_LENGTH(d)');
    } else {
      $result = await $db->queryf('SELECT * FROM countries WHERE enabled = 1 ORDER BY name');
    }

    $countries = array();
    foreach ($result->mapRows() as $row) {
      $countries[] = self::countryFromRow($row);
    }

    return $countries;
  }

  public static async function genAllMapCountries(
  ): Awaitable<array<Country>> {
    $db = await self::genDb();
    
    $result = await $db->queryf(
      'SELECT * FROM countries ORDER BY CHAR_LENGTH(d)',
    );

    $countries = array();
    foreach ($result->mapRows() as $row) {
      $countries[] = self::countryFromRow($row);
    }

    return $countries;
  }

  // All enabled and unused countries
  public static async function genAllAvailableCountries(
  ): Awaitable<array<Country>> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM countries WHERE enabled = 1 AND used = 0 ORDER BY name',
    );

    $countries = array();
    foreach ($result->mapRows() as $row) {
      $countries[] = self::countryFromRow($row);
    }

    return $countries;
  }

  // Check if country is in an active level
  public static async function genIsActiveLevel(
    int $country_id,
  ): Awaitable<bool> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT COUNT(*) FROM levels WHERE entity_id = %d AND active = 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval($result->mapRows()[0]['COUNT(*)']) > 0;
  }

  // Get a country by id
  public static async function gen(
    int $country_id,
  ): Awaitable<Country> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT * FROM countries WHERE id = %d LIMIT 1',
      $country_id,
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return self::countryFromRow($result->mapRows()[0]);
  }

  // Get a random enabled, unused country ID
  public static async function genRandomAvailableCountryId(
  ): Awaitable<int> {
    $db = await self::genDb();

    $result = await $db->queryf(
      'SELECT id FROM countries WHERE enabled = 1 AND used = 0 ORDER BY RAND() LIMIT 1',
    );

    invariant($result->numRows() === 1, 'Expected exactly one result');
    return intval($result->mapRows()[0]['id']);
  }

  private static function countryFromRow(Map<string, string> $row): Country {
    return new Country(
      intval(must_have_idx($row, 'id')),
      must_have_idx($row, 'iso_code'),
      must_have_idx($row, 'name'),
      intval(must_have_idx($row, 'used')),
      intval(must_have_idx($row, 'enabled')),
      must_have_idx($row, 'd'),
      must_have_idx($row, 'transform'),
    );
  }
}
