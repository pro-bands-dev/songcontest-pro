# Song Contest PRO – WordPress Plugin

A lightweight **WordPress plugin for running a music / band contest** with applications, voting, charts and winner display.

Designed for music studios, labels, band contests and online music competitions.

---

# Features

### Band Application System

Bands can submit their entry through a form including:

* Band name
* Contact email
* Song title
* Band description

Submissions are stored in the WordPress database.

---

### Voting System

Visitors can vote for their favorite band.

Features:

* 1 vote per IP per day
* AJAX voting
* Automatic vote counting
* Ranking by votes

---

### Jury Voting

Optional jury voting.

* Only logged-in users can vote
* Separate jury voting page

---

### Contest Phases

Display the current contest phase:

* Application phase
* Top 20 voting
* Final
* Winner announcement

Shortcode:

```
[songcontest_phase]
```

---

### Voting Countdown

Countdown timer until the voting deadline.

Shortcode:

```
[songcontest_countdown]
```

---

### Band Profile Pages

Each band gets a profile page.

Example URL:

```
https://your-site.com/?band=12
```

Shortcode used on the page:

```
[songcontest_band]
```

---

### Live Charts

Displays the **Top 10 bands** automatically.

Shortcode:

```
[songcontest_charts]
```

---

### Winner Page

Displays the band with the most votes.

Shortcode:

```
[songcontest_winner]
```

---

# Installation

1. Download the repository

2. Upload the plugin folder to:

```
/wp-content/plugins/songcontest-pro
```

3. Activate the plugin in WordPress:

```
WordPress Dashboard
Plugins
Activate "Song Contest PRO"
```

The required database tables will be created automatically.

---

# Shortcodes

## Application Form

```
[songcontest_application]
```

Allows bands to submit their application.

---

## Voting Page

```
[songcontest_voting]
```

Displays all bands and allows visitors to vote.

---

## Jury Voting

```
[songcontest_jury_voting]
```

Only accessible for logged-in users.

---

## Contest Phase

```
[songcontest_phase]
```

Shows the current contest phase.

---

## Voting Countdown

```
[songcontest_countdown]
```

Displays a countdown timer until the voting ends.

---

## Charts

```
[songcontest_charts]
```

Shows the Top 10 bands.

---

## Winner

```
[songcontest_winner]
```

Displays the winning band.

---

# Recommended Contest Page Layout

Example page structure:

```
Hero
Contest Phase
Voting Countdown
Charts
Voting
Application
Winner
```

Example page content:

```
[songcontest_phase]

[songcontest_countdown]

[songcontest_charts]

[songcontest_voting]

[songcontest_application]

[songcontest_winner]
```

---

# Database Tables

The plugin creates two tables:

```
wp_scp_artists
wp_scp_votes
```

---

# Styling

Default design includes:

* Font: Montserrat
* Body font size: 16px
* Line height: 1.5
* Highlight color: #e99a2c

Buttons:

```
Background: #e99a2c
Text: #ffffff
Border radius: 3px
Font size: 20px
```

---

# License

This plugin is released under the MIT License.

You are free to modify and extend it.

---

# Author

Created for running online **song contests and band competitions** on WordPress.
# songcontest-pro
Song-Contest Plugin für das Pro-Bands Song-Contest 2026
