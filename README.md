# Web-powerdby-Nanndemoya
何でも屋が提供するWebサイト編集アプリ

## Run in Google Colab

You can start the app from Google Colab with a single code cell. Create a new Colab notebook, add a code cell and paste the following as the entire cell (include the leading %%bash):

```
%%bash
# then paste the contents of scripts/colab_one_cell_launch.sh here
```

Run the cell and wait — the script installs Node.js, clones the repo, installs dependencies, starts the dev server, and exposes it with localtunnel. The cell output will include a public URL you can open in your browser.

Notes: Colab sessions are temporary. If localtunnel is unavailable, use ngrok with your authtoken (not included here).
