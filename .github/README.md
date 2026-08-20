# [_DEFAULTREPOS](https://github.com/n138-kz/_DEFAULTREPOS)

## Quick setup

<details>
  <summary>if you’ve done this kind of thing before</summary>

  ```sh
  git clone https://github.com/n138-kz/_DEFAULTREPOS.git
  ```

  ```sh
  git clone git@github.com:n138-kz/_DEFAULTREPOS.git
  ```

</details>
<details>
  <summary>…or create a new repository on the command line / 新しいリポジトリを作成する</summary>

  ```sh
  touch README.md
  git init
  git add README.md
  git commit -m init
  git branch -M main
  git remote add origin git@github.com:n138-kz/_DEFAULTREPOS.git
  git branch --set-upstream-to=origin/main main
  git config pull.rebase false
  git fetch
  git pull
  git merge --allow-unrelated-histories origin/main
  git push -u origin main
  ```

- [git merge --allow-unrelated-histories origin/main](https://qiita.com/mei28/items/85bc881ac1f26332ac15)


</details>
<details>
  <summary>…or push an existing repository from the command line / 既存のリポジトリをプッシュする</summary>

  ```sh
  git remote add origin git@github.com:n138-kz/_DEFAULTREPOS.git
  git branch -M main
  git push -u origin main
  ```


</details>
<details>
  <summary>pull an another existing repository from the command line / 既存のリポジトリをプルする</summary>

  ```sh
  git config pull.rebase false
  git branch -r
  git pull origin {remote-repos-name}:{local-repos-name}
  git checkout {local-repos-name}
  git branch --set-upstream-to=origin/{remote-repos-name} {local-repos-name}
  ```

</details>

## github repository initial setup

1. ["Actions secrets and variables"](https://github.com/n138-kz/_DEFAULTREPOS/settings/secrets/actions) > "Repository secrets" 移動し、`DISCORD_WEBHOOK_URL` を設定  
  値は Discord webhook url を設定

## Repos Info

<div align="center">

  [![GitHub repo license](https://img.shields.io/github/license/n138-kz/_DEFAULTREPOS)](/LICENSE)
  [![GitHub top language](https://img.shields.io/github/languages/top/n138-kz/_DEFAULTREPOS)](/../../)
  [![GitHub repo size](https://img.shields.io/github/repo-size/n138-kz/_DEFAULTREPOS)](/../../)
  [![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/n138-kz/_DEFAULTREPOS)](/../../)

</div>
<div align="center">

  [![GitHub last commit](https://img.shields.io/github/last-commit/n138-kz/_DEFAULTREPOS)](/../../commits)
  [![GitHub commit activity](https://img.shields.io/github/commit-activity/w/n138-kz/_DEFAULTREPOS)](/../../commits)
  [![GitHub commit activity](https://img.shields.io/github/commit-activity/t/n138-kz/_DEFAULTREPOS)](/../../commits)
  [![GitHub language count](https://img.shields.io/github/languages/count/n138-kz/_DEFAULTREPOS)](/../../)

</div>
<div align="center">

  [![GitHub issues](https://img.shields.io/github/issues/n138-kz/_DEFAULTREPOS)](/../../issues)
  [![GitHub issues closed](https://img.shields.io/github/issues-closed/n138-kz/_DEFAULTREPOS)](/../../issues)
  [![GitHub pull requests](https://img.shields.io/github/issues-pr/n138-kz/_DEFAULTREPOS)](/../../pulls)
  [![GitHub pull requests closed](https://img.shields.io/github/issues-pr-closed/n138-kz/_DEFAULTREPOS)](/../../pulls)

</div>
<div align="center">

  [![](https://img.shields.io/badge/YouTube-FF0000?style=for-the-badge&logo=youtube&logoColor=white)](https://youtube.com/channel/UCOX8Iv1r0V18lbOnohE7lWQ)
  [![](https://img.shields.io/badge/Twitch-6441A5?style=for-the-badge&logo=twitch&logoColor=white)](https://www.twitch.tv/yuukomiya)
  [![](https://img.shields.io/badge/X-000000?style=for-the-badge&logo=x&logoColor=white)](https://x.com/n138kz)
  <br>
  [![](https://img.shields.io/youtube/channel/subscribers/UCOX8Iv1r0V18lbOnohE7lWQ)](https://youtube.com/channel/UCOX8Iv1r0V18lbOnohE7lWQ)
  [![](https://img.shields.io/twitch/status/YuuKomiya)](https://www.twitch.tv/yuukomiya)

</div>

## Refs

- [![](https://www.google.com/s2/favicons?size=64&domain=https://github.com)_DEFAULTREPOS](https://github.com/n138-kz/_DEFAULTREPOS/)

## License

[Copyright (c) 2026 Yuu Komiya (n138), Under MIT License](LICENSE)  
