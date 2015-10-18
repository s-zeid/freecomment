all: freecomment.php

freecomment.php: src/app.php src/super-mailer-bros.php src/freecomment.php
	$(foreach f,$^,php -l $(f);)
	printf '<?php' > $@
	for f in $^; do \
	 cat $$f | sed -e '1s/<?php//g' | sed -e '$$s/?>//g' >> $@; \
	done
	printf '?>\n' >> $@
	sed -i -e 's/require(['"'"'"][^.]\+\.php['"'"'"]);//g' $@

.PHONY: clean
clean:
	rm -f freecomment.php
