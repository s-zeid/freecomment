all: freecomment.php

freecomment.php: src/app.php src/super-mailer-bros.php src/freecomment.php
	$(foreach f,$^,php -l $(f);)
	cat $^ > $@
	sed -i -e '2~1s/<?php//g' $@
	sed -i -e 's/require(['"'"'"][^.]\+\.php['"'"'"]);//g' $@

.PHONY: clean
clean:
	rm -f freecomment.php
