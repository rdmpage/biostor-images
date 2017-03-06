# biostor-images
Extract figures and tables from BioStor articles

## Idea
Using ABBYY OCR files on Internet Archive we can identify and extract images from BioStor articles. Next step would be to extract caption text as well.

## Upload to IA

Images extracted from articles can be added back to Internet Archive items, so they appear in the directory listing. IA automatically derives thumbnails (can take a little while). Just use curl, e.g. to upload an image to biostor-115612

```
curl --location --header "x-archive-queue-derive:0" --header "authorization: LOW <key1>:<key2>“ --upload-file tmp/7-0.jpg http://s3.us.archive.org/biostor-115612/biostor-115612_7-0.jpg
```



