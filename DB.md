# Tournament ER Schema

```dbml
enum match_record_status {
  scheduled
  in_progress
  completed
  cancelled
}

enum tournament_status {
  scheduled
  registrations_opened
  registrations_closed
  in_progress
  completed
  cancelled
}

enum match_record_end_method {
  victory_unanimous_decision
  victory_split_decision
  victory_ko
  victory_tko
  victory_disqualification
  draw
  no_contest
}

enum athlete_gender {
  male
  female
  hybrid
}

Table users {
  id                uuid        [pk, default: `gen_random_uuid()`]
  username          varchar     [not null, unique]
  email             varchar     [not null, unique]
  email_verified_at timestamptz [null]
  superadmin        boolean     [not null, default: false]
  password          varchar     [not null]
  remember_token    varchar(100) [null]
  created_at        timestamptz [null]
  updated_at        timestamptz [null]
}

Table personal_access_tokens {
  id              bigint      [pk, increment]
  tokenable_type  varchar     [not null]
  tokenable_id    uuid        [not null]
  name            text        [not null]
  token           varchar(64) [not null, unique]
  abilities       text        [null]
  last_used_at    timestamptz [null]
  expires_at      timestamptz [null]
  created_at      timestamptz [null]
  updated_at      timestamptz [null]

  indexes {
    (tokenable_type, tokenable_id) [name: 'personal_access_tokens_tokenable_type_tokenable_id_index']
    expires_at [name: 'personal_access_tokens_expires_at_index']
  }
}

Table weight_categories {
  id         uuid        [pk]
  label      varchar     [not null]
  value      decimal(8,2) [not null]
  created_at timestamptz [null]
  updated_at timestamptz [null]
}

Table disciplines {
  id                uuid    [pk]
  label             varchar [not null]
  rounds            int     [not null]
  minutes_per_round varchar [not null]
  created_at        timestamptz [null]
  updated_at        timestamptz [null]
}

Table athletes {
  id                         uuid    [pk]
  first_name                 varchar [not null]
  last_name                  varchar [not null]
  full_name                  varchar [not null]
  birth_date                 date    [not null]
  gender                     athlete_gender [not null]
  tax_number                 varchar [not null, unique]
  team_name                  varchar [null]
  default_weight_category_id uuid    [null, ref: > weight_categories.id]
  default_discipline_id      uuid    [null, ref: > disciplines.id]
  created_at                 timestamptz [null]
  updated_at                 timestamptz [null]
}

Table tournaments {
  id               uuid              [pk]
  name             varchar           [not null]
  location_name    varchar           [not null]
  location_address varchar           [not null]
  location_city    varchar           [not null]
  date_from        date              [not null]
  date_to          date              [not null]
  status           tournament_status [not null]
  created_at       timestamptz       [null]
  updated_at       timestamptz       [null]
}

Table experience_tiers {
  id              uuid        [pk]
  tournament_id   uuid        [null, ref: > tournaments.id]  // null = global default; a tournament's own enabled tiers replace the globals entirely
  label           varchar     [not null]
  min_match_count int         [not null]
  max_match_count int         [null]    // null = unbounded
  enabled         boolean     [not null, default: false]
  created_at      timestamptz [null]
  updated_at      timestamptz [null]
}

Table registrations {
  id                 uuid        [pk]
  athlete_id         uuid        [not null, ref: > athletes.id]
  tournament_id      uuid        [not null, ref: > tournaments.id]
  discipline_id      uuid        [not null, ref: > disciplines.id]
  weight_category_id uuid        [not null, ref: > weight_categories.id]
  paid_at            timestamptz [null]
  arrived            boolean     [not null, default: false]
  weight_in          decimal(8,2) [null]
  notes              text        [null]
  created_at         timestamptz [null]
  updated_at         timestamptz [null]
}

Table match_records {
  id                 uuid         [pk]
  tournament_id      uuid         [not null, ref: > tournaments.id]
  red_corner_id      uuid         [null, ref: > athletes.id]     // null = half bout; matchmaking always fills this corner first
  blue_corner_id     uuid         [null, ref: > athletes.id]     // null = half bout, athlete waiting for an opponent
  weight_category_id uuid         [not null, ref: > weight_categories.id]
  discipline_id      uuid         [not null, ref: > disciplines.id]
  gender             athlete_gender [not null]
  forced             boolean      [not null, default: false]
  red_corner_team    varchar      [null]     // null when there is no red corner yet
  blue_corner_team   varchar      [null]     // null when there is no blue corner yet
  sort               int          [not null]
  scheduled_time     time         [null]
  winner_id          uuid         [null, ref: > athletes.id]
  end_round          varchar      [null]
  end_method         match_record_end_method [null]
  status             match_record_status     [not null]
  rounds             int          [not null]
  minutes_per_round  varchar      [not null]
  judges_points      json         [null]   // array of per-round scores: [{round, redCornerJudge1, redCornerJudge2, redCornerJudge3, blueCornerJudge1, blueCornerJudge2, blueCornerJudge3}] — int or null if judge not assigned
  notes              text         [null]
  created_at         timestamptz  [null]
  updated_at         timestamptz  [null]
}

Table media {
  id                   bigint  [pk, increment]
  model_type           varchar [not null]
  model_id             uuid    [not null]
  uuid                 uuid    [null, unique]
  collection_name      varchar [not null]
  name                 varchar [not null]
  file_name            varchar [not null]
  mime_type            varchar [null]
  disk                 varchar [not null]
  conversions_disk     varchar [null]
  size                 bigint  [not null]
  manipulations        json    [not null]
  custom_properties    json    [not null]
  generated_conversions json   [not null]
  responsive_images    json    [not null]
  order_column         int     [null]
  created_at           timestamptz [null]
  updated_at           timestamptz [null]
}
```
