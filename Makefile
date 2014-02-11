all: freecomment.php

freecomment.php: src/app.php src/freecomment.php
	cat $^ > $@
	sed -i -e '2~1s/<?php//g' $@
	sed -i -e 's/require(['"'"'"]app\.php['"'"'"]);//g' $@

.PHONY: clean
clean:
	rm -f freecomment.php
