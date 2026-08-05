# DB_SEED — Seed Data Reference

This document describes the dev seed data loaded at application startup when the `dev` profile is active. Use it as the source of truth when reimplementing seeders on another framework.

---

## Execution order

Seeders run sequentially at startup (idempotent: each checks `count() > 0` and skips if data already exists).

| Order | Seeder               | Profile |
|-------|----------------------|---------|
| 1     | UserSeeder           | all     |
| 2     | WeightCategorySeeder | dev     |
| 3     | DisciplineSeeder     | dev     |
| 4     | ExperienceTierSeeder | all     |
| 5     | AthleteSeeder        | dev     |
| 6     | TournamentSeeder     | dev     |
| 7     | RegistrationSeeder   | dev     |
| 8     | MatchSeeder          | dev     |

`UserSeeder` runs on every profile (no profile restriction). `ExperienceTierSeeder` must also run everywhere: matchmaking reads its tiers from the database, and with none enabled no athlete can be paired. All others are `dev`-only.

---

## 1. Users

One superadmin user, always seeded.

| Field      | Value                     |
|------------|---------------------------|
| email      | `superadmin@matches.it`   |
| password   | `12345678` (bcrypt-hashed) |
| superadmin | `true`                    |

---

## 2. Weight Categories

5 records.

| Label | Value (kg) |
|-------|------------|
| 66Kg  | 66.0       |
| 68Kg  | 68.0       |
| 70Kg  | 70.0       |
| 73Kg  | 73.0       |
| 80Kg  | 80.0       |

---

## 3. Disciplines

7 records.

| Label           |
|-----------------|
| MMA A           |
| MMA B           |
| MMA D           |
| K1 Full         |
| K1 Light        |
| Muay Thai Full  |
| Muay Thai Light |

---

## 4. Experience Tiers

3 records, all global (`tournamentId` = null) and all enabled. They reproduce the thresholds matchmaking used to have hardcoded.

| Label        | minMatchCount | maxMatchCount | enabled |
|--------------|---------------|---------------|---------|
| beginner     | 0             | 4             | true    |
| intermediate | 5             | 15            | true    |
| advanced     | 16            | null (unbounded) | true |

A tier bound to a tournament overrides the globals for that tournament: if it has at least one *enabled* tier of its own, those replace the global set entirely. Athletes whose match count falls outside every enabled tier are not paired and show up in `matchmaking_issues` with `reason: no_tier`.

No tournament-scoped tiers are seeded.

---

## 5. Athletes

160 records total: 80 male + 80 female. All born on `1995-01-01`.

**Males (M001–M080)**

- `firstName`: `Atleta`
- `lastName`: `M001` … `M080` (zero-padded, 3 digits)
- `gender`: `MALE`
- `taxNumber`: pattern `ATLTM{NNN}00000000` (e.g. `ATLTM00100000000`)
- `teamName`: `Team M001` … `Team M080`

**Females (F001–F080)**

- `firstName`: `Atleta`
- `lastName`: `F001` … `F080`
- `gender`: `FEMALE`
- `taxNumber`: pattern `ATLTF{NNN}00000000` (e.g. `ATLTF00100000000`)
- `teamName`: `Team F001` … `Team F080`

---

## 6. Tournaments

3 records.

| Name     | locationName | locationAddress  | locationCity | date       | status      |
|----------|--------------|------------------|--------------|------------|-------------|
| Torneo 1 | PalaMilano   | Via Lomazzo 10   | Milano       | 2025-01-18 | COMPLETED   |
| Torneo 2 | PalaFlorio   | Via Amoroso 2    | Bari         | 2026-05-27 | IN_PROGRESS |
| Torneo 3 | Unipol Arena | Via Gino Cervi 2 | Bologna      | 2026-08-15 | SCHEDULED   |

---

## 7. Registrations

Registrations link an athlete to a tournament with a discipline and weight category. All registrations have:

- `paidAt`: current timestamp at seed time
- `arrived`: `true`
- `weightIn`: numeric value of the weight category (e.g. `66.0` for 66Kg)

No registration status field is set (uses entity default).

### Torneo 1 — 20 registrations (10 pairs)

Each row produces 2 registrations (one per athlete).

| Red corner  | Blue corner  | Weight | Discipline      |
|-------------|--------------|--------|-----------------|
| Atleta M071 | Atleta M072  | 66Kg   | MMA A           |
| Atleta M073 | Atleta M074  | 68Kg   | K1 Full         |
| Atleta M075 | Atleta M076  | 70Kg   | MMA D           |
| Atleta M077 | Atleta M078  | 73Kg   | Muay Thai Light |
| Atleta M079 | Atleta M080  | 80Kg   | K1 Full         |
| Atleta F071 | Atleta F072  | 66Kg   | K1 Light        |
| Atleta F073 | Atleta F074  | 68Kg   | MMA A           |
| Atleta F075 | Atleta F076  | 70Kg   | MMA B           |
| Atleta F077 | Atleta F078  | 73Kg   | MMA D           |
| Atleta F079 | Atleta F080  | 80Kg   | Muay Thai Full  |

### Torneo 2 — 160 registrations (80 pairs)

**Males 66Kg**

| Red         | Blue         | Discipline      |
|-------------|--------------|-----------------|
| Atleta M071 | Atleta M072  | MMA A           |
| Atleta M001 | Atleta M002  | MMA A           |
| Atleta M003 | Atleta M004  | MMA B           |
| Atleta M005 | Atleta M006  | MMA B           |
| Atleta M007 | Atleta M008  | K1 Full         |
| Atleta M009 | Atleta M010  | K1 Full         |
| Atleta M011 | Atleta M012  | Muay Thai Light |
| Atleta M013 | Atleta M014  | Muay Thai Light |

**Males 68Kg**

| Red         | Blue         | Discipline      |
|-------------|--------------|-----------------|
| Atleta M015 | Atleta M016  | MMA A           |
| Atleta M017 | Atleta M018  | MMA A           |
| Atleta M019 | Atleta M020  | MMA B           |
| Atleta M021 | Atleta M022  | MMA B           |
| Atleta M073 | Atleta M074  | K1 Full         |
| Atleta M023 | Atleta M024  | K1 Full         |
| Atleta M025 | Atleta M026  | Muay Thai Light |
| Atleta M027 | Atleta M028  | Muay Thai Light |

**Males 70Kg**

| Red         | Blue         | Discipline      |
|-------------|--------------|-----------------|
| Atleta M075 | Atleta M076  | MMA A           |
| Atleta M029 | Atleta M030  | MMA A           |
| Atleta M031 | Atleta M032  | MMA B           |
| Atleta M033 | Atleta M034  | MMA B           |
| Atleta M035 | Atleta M036  | K1 Full         |
| Atleta M037 | Atleta M038  | K1 Full         |
| Atleta M039 | Atleta M040  | Muay Thai Light |
| Atleta M041 | Atleta M042  | Muay Thai Light |

**Males 73Kg**

| Red         | Blue         | Discipline      |
|-------------|--------------|-----------------|
| Atleta M043 | Atleta M044  | MMA A           |
| Atleta M045 | Atleta M046  | MMA A           |
| Atleta M047 | Atleta M048  | MMA B           |
| Atleta M049 | Atleta M050  | MMA B           |
| Atleta M051 | Atleta M052  | K1 Full         |
| Atleta M053 | Atleta M054  | K1 Full         |
| Atleta M077 | Atleta M078  | Muay Thai Light |
| Atleta M055 | Atleta M056  | Muay Thai Light |

**Males 80Kg**

| Red         | Blue         | Discipline      |
|-------------|--------------|-----------------|
| Atleta M057 | Atleta M058  | MMA A           |
| Atleta M059 | Atleta M060  | MMA A           |
| Atleta M061 | Atleta M062  | MMA B           |
| Atleta M063 | Atleta M064  | MMA B           |
| Atleta M079 | Atleta M080  | K1 Full         |
| Atleta M065 | Atleta M066  | K1 Full         |
| Atleta M067 | Atleta M068  | Muay Thai Light |
| Atleta M069 | Atleta M070  | Muay Thai Light |

**Females 66Kg**

| Red         | Blue         | Discipline     |
|-------------|--------------|----------------|
| Atleta F001 | Atleta F002  | MMA A          |
| Atleta F003 | Atleta F004  | MMA A          |
| Atleta F005 | Atleta F006  | MMA D          |
| Atleta F007 | Atleta F008  | MMA D          |
| Atleta F071 | Atleta F072  | K1 Light       |
| Atleta F009 | Atleta F010  | K1 Light       |
| Atleta F011 | Atleta F012  | Muay Thai Full |
| Atleta F013 | Atleta F014  | Muay Thai Full |

**Females 68Kg**

| Red         | Blue         | Discipline     |
|-------------|--------------|----------------|
| Atleta F073 | Atleta F074  | MMA A          |
| Atleta F015 | Atleta F016  | MMA A          |
| Atleta F017 | Atleta F018  | MMA D          |
| Atleta F019 | Atleta F020  | MMA D          |
| Atleta F021 | Atleta F022  | K1 Light       |
| Atleta F023 | Atleta F024  | K1 Light       |
| Atleta F025 | Atleta F026  | Muay Thai Full |
| Atleta F027 | Atleta F028  | Muay Thai Full |

**Females 70Kg**

| Red         | Blue         | Discipline     |
|-------------|--------------|----------------|
| Atleta F029 | Atleta F030  | MMA A          |
| Atleta F031 | Atleta F032  | MMA A          |
| Atleta F075 | Atleta F076  | MMA D          |
| Atleta F033 | Atleta F034  | MMA D          |
| Atleta F035 | Atleta F036  | K1 Light       |
| Atleta F037 | Atleta F038  | K1 Light       |
| Atleta F039 | Atleta F040  | Muay Thai Full |
| Atleta F041 | Atleta F042  | Muay Thai Full |

**Females 73Kg**

| Red         | Blue         | Discipline     |
|-------------|--------------|----------------|
| Atleta F043 | Atleta F044  | MMA A          |
| Atleta F045 | Atleta F046  | MMA A          |
| Atleta F077 | Atleta F078  | MMA D          |
| Atleta F047 | Atleta F048  | MMA D          |
| Atleta F049 | Atleta F050  | K1 Light       |
| Atleta F051 | Atleta F052  | K1 Light       |
| Atleta F053 | Atleta F054  | Muay Thai Full |
| Atleta F055 | Atleta F056  | Muay Thai Full |

**Females 80Kg**

| Red         | Blue         | Discipline     |
|-------------|--------------|----------------|
| Atleta F057 | Atleta F058  | MMA A          |
| Atleta F059 | Atleta F060  | MMA A          |
| Atleta F061 | Atleta F062  | MMA D          |
| Atleta F063 | Atleta F064  | MMA D          |
| Atleta F065 | Atleta F066  | K1 Light       |
| Atleta F067 | Atleta F068  | K1 Light       |
| Atleta F079 | Atleta F080  | Muay Thai Full |
| Atleta F069 | Atleta F070  | Muay Thai Full |

### Torneo 3 — no registrations

---

## 8. Matches

Matches use the same pair lists as registrations. All matches have:

- `rounds`: 3
- `minutesPerRound`: 3.0
- `forced`: `true`
- `redCornerTeam` / `blueCornerTeam`: copied from athlete's `teamName`
- `gender`: copied from the red corner athlete's gender
- `sort`: 1-based index within the tournament

### Torneo 1 — 10 matches

Same 10 pairs as Torneo 1 registrations (indexed 0–9). Status is assigned by even/odd index:

- **Even index (0,2,4,6,8)** → `COMPLETED`
  - `endMethod` cycles through (in order of completed matches): `VICTORY_KO`, `VICTORY_UNANIMOUS_DECISION`, `VICTORY_TKO`, `VICTORY_SPLIT_DECISION`, `VICTORY_DISQUALIFICATION`
  - `winner` alternates: even completed-count → red corner; odd → blue corner
- **Odd index (1,3,5,7,9)** → `CANCELLED`

No `judgesPoints` are set for Torneo 1 matches.

### Torneo 2 — 80 matches

Same 80 pairs as Torneo 2 registrations (indices 0–79).

**Indices 0–38 (first 39 matches)**

- `i % 3 != 2` → `COMPLETED`:
  - `endMethod` cycles through 6 values: `VICTORY_KO`, `VICTORY_UNANIMOUS_DECISION`, `VICTORY_TKO`, `VICTORY_SPLIT_DECISION`, `VICTORY_DISQUALIFICATION`, `DRAW`
  - `winner`: if `DRAW` → none; otherwise alternates red/blue by completed-count parity
  - `judgesPoints`: cycles through JP_A, JP_B, JP_C by `completedCount % 3`
- `i % 3 == 2` → `CANCELLED`

**Index 39** → `IN_PROGRESS` (no endMethod, no winner)

**Indices 40–79** → `SCHEDULED`

### Judges points patterns

Each `JudgesPointsEntry` contains: `round`, `judge1Red`, `judge1Blue`, `judge2Red`, `judge2Blue`, `judge3Red`, `judge3Blue`. Null values mean that round/judge score was not entered.

**JP_A** — 3 fully scored rounds

| Round | J1 Red | J1 Blue | J2 Red | J2 Blue | J3 Red | J3 Blue |
|-------|--------|---------|--------|---------|--------|---------|
| 1     | 10     | 9       | 10     | 9       | 10     | 9       |
| 2     | 10     | 10      | 9      | 9       | 9      | 10      |
| 3     | 9      | 10      | 10     | 10      | 9      | 9       |

**JP_B** — 2 scored rounds, round 3 empty

| Round | J1 Red | J1 Blue | J2 Red | J2 Blue | J3 Red | J3 Blue |
|-------|--------|---------|--------|---------|--------|---------|
| 1     | 10     | 10      | 10     | 9       | 9      | 9       |
| 2     | 10     | 10      | null   | 9       | 9      | null    |
| 3     | null   | null    | null   | null    | null   | null    |

**JP_C** — 1 scored round, rounds 2–3 empty

| Round | J1 Red | J1 Blue | J2 Red | J2 Blue | J3 Red | J3 Blue |
|-------|--------|---------|--------|---------|--------|---------|
| 1     | 10     | 10      | null   | 9       | 9      | null    |
| 2     | null   | null    | null   | null    | null   | null    |
| 3     | null   | null    | null   | null    | null   | null    |

### Torneo 3 — no matches
