# Tournament ER Schema

```dbml
enum match_status {
  SCHEDULED
  IN_PROGRESS
  COMPLETED
  CANCELLED
}

enum tournament_status {
  SCHEDULED
  REGISTRATIONS_OPENED
  REGISTRATIONS_CLOSED
  IN_PROGRESS
  COMPLETED
  CANCELLED
}

enum end_method {
  VICTORY_UNANIMOUS_DECISION
  VICTORY_SPLIT_DECISION
  VICTORY_KO
  VICTORY_TKO
  VICTORY_DISQUALIFICATION
  DRAW
  NO_CONTEST
}

enum gender {
  MALE
  FEMALE
  HYBRID
}

enum client_type {
  DESKTOP
  MOBILE
}

Table users {
  id         uuid        [pk, default: `gen_random_uuid()`]
  email      varchar     [not null, unique]
  superadmin boolean     [not null, default: false]
  password   varchar     [not null]
  created_at timestamptz [not null, default: `now()`]
}

Table personal_access_tokens {
  id           bigint      [pk, increment]
  user_id      uuid        [not null, ref: > users.id]
  name         varchar     [not null]
  token_hash   varchar(64) [not null, unique]
  client_type  client_type [not null]
  last_used_at timestamptz [null]
  expires_at   timestamptz [null]
  created_at   timestamptz [not null, default: `now()`]

  indexes {
    token_hash [name: 'idx_pat_token_hash']
  }
}

Table token_abilities {
  token_id bigint  [not null, ref: > personal_access_tokens.id]
  ability  varchar [not null]
}

Table weight_categories {
  id    uuid        [pk]
  label varchar     [not null]
  value decimal     [not null]
}

Table disciplines {
  id    uuid    [pk]
  label varchar [not null]
}

Table athletes {
  id                          uuid   [pk]
  first_name                  varchar [not null]
  last_name                   varchar [not null]
  birth_date                  date    [not null]
  gender                      gender  [not null]
  tax_number                  varchar [not null, unique]
  team_name                   varchar [null]
  default_weight_category_id  uuid    [null, ref: > weight_categories.id]
  default_discipline_id       uuid    [null, ref: > disciplines.id]
}

Table tournaments {
  id               uuid              [pk]
  name             varchar           [not null]
  location_name    varchar           [not null]
  location_address varchar           [not null]
  location_city    varchar           [not null]
  date             date              [not null]
  status           tournament_status [not null]
}

Table registrations {
  id                 uuid    [pk]
  athlete_id         uuid    [not null, ref: > athletes.id]
  tournament_id      uuid    [not null, ref: > tournaments.id]
  discipline_id      uuid    [not null, ref: > disciplines.id]
  weight_category_id uuid    [not null, ref: > weight_categories.id]
  paid_at            timestamptz [null]
  arrived            boolean [not null, default: false]
  weight_in          decimal [null]
  notes              text    [null]

  indexes {
    (athlete_id, tournament_id) [unique, name: 'uq_registrations_athlete_tournament']
  }
}

Table matches {
  id                 uuid         [pk]
  tournament_id      uuid         [not null, ref: > tournaments.id]
  red_corner_id      uuid         [not null, ref: > athletes.id]
  blue_corner_id     uuid         [not null, ref: > athletes.id]
  weight_category_id uuid         [not null, ref: > weight_categories.id]
  discipline_id      uuid         [not null, ref: > disciplines.id]
  gender             gender       [not null]
  forced             boolean      [not null, default: false]
  red_corner_team    varchar      [not null]
  blue_corner_team   varchar      [not null]
  sort               int          [not null]
  scheduled_time     time         [null]
  winner_id          uuid         [null, ref: > athletes.id]
  end_round          varchar      [null]
  end_method         end_method   [null]
  status             match_status [not null]
  rounds             int          [not null]
  minutes_per_round  float        [not null]
  judges_points      jsonb        [null]   // array of per-round scores: [{round, redCornerJudge1, redCornerJudge2, redCornerJudge3, blueCornerJudge1, blueCornerJudge2, blueCornerJudge3}] — int or null if judge not assigned
}
```
