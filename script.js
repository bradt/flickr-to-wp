(function($) {

    $(document).ready(function() {
        
        $('.migrate-sets .more').click(function() {
            var $tr = $(this).parents('tr');
            var set_flickr_id = $('input', $tr).val();
            var data = {
                action : 'photoset_info',
                flickr_id : set_flickr_id
            };
            $.post(ajaxurl, data, function(resp) {
                resp = JSON.parse(resp);
                var desc = (resp.description) ? resp.description + '<br />' : '';
                var date = new Date(resp.date_create * 1000);
                $('.title', $tr).append('<div class="more-info">' + desc + 'Photos: ' + resp.photos + '&nbsp;&nbsp;&nbsp;Date Created: ' + date.getFullYear() + '-' + (date.getMonth()+1) + '-' + date.getDate() + '</div>');
                $('.more', $tr).remove();
            });
            return false;
        });
        
        $('.select-controls a').click(function() {
            $('input[type=checkbox]').attr('checked', $(this).hasClass('select-all'));
            return false;
        });
        
        $('input.migrate-photos').click(function() {
            
            var sets = new PhotoSets();
            $('input.set-ids:checked').each(function() {
                var $table_row = $(this).parents('tr').eq(0);
                sets.addSet($table_row, $(this).val(), $(this).attr('rel'));
            });
            
            sets.migrateNextSet();

            return false;
        });
        
        $('input.collection-ids').click(function() {
            var id = $(this).val();
            var checked = ($(this).attr('checked')) ? true : false;
            $('input.collection-' + id).attr('checked', checked);
        });
        
    });
    
    function PhotoSets() {
        var self = this;
        this.sets = new Array();
        
        this.migrateNextSet = function() {
            var set = self.sets.shift();
            if (!set) return;
            set.migrate();
        };
        
        this.addSet = function(table_row, flickr_id, collection_term_id) {
            self.sets.push(new PhotoSet(this, table_row, flickr_id, collection_term_id));
        };
    }
    
    function PhotoSet(sets, table_row, flickr_id, collection_term_id) {
        var self = this;
        this.sets = sets;
        this.table_row = table_row;
        this.flickr_id = flickr_id;
        this.collection_term_id = collection_term_id;
        this.wp_id = 0;
        this.photos = new Array();
        this.photo_count = 0;
        this.photo_num = 0;
        
        this.scrollToRow = function() {
            $.scrollTo(self.table_row, { offset: { top: -20 } });
        };
        
        this.migrateNextPhoto = function() {
            var photo = self.photos.shift();
            if (!photo) {
                var msg = 'Complete. Imported ' + self.photo_count + ' photos.';
                if (!self.photo_count) {
                    msg += ' Looks like you may not have access to these photos. Maybe you need to upgrade to Flickr Pro?';
                }
                self.updateProgress(msg, 100);
                return self.sets.migrateNextSet();
            }
            self.photo_num = self.photo_num + 1;
            photo.migrate();
        };
        
        this.migrate = function() {
            var data = {
                action : 'migrate_set',
                set_flickr_id : self.flickr_id,
                collection_term_id : self.collection_term_id
            };
            $.post(ajaxurl, data, function(resp) {
                if (!resp) return self.updateProgress('Error downloading set!');

                resp = JSON.parse(resp);
                self.wp_id = resp.set_wp_id;
                self.photo_count = resp.photos.length;
                for (i in resp.photos) {
                    var p = resp.photos[i];
                    self.photos.push(new Photo(self, p.flickr_id, p.url, p.isprimary));
                }
                
                self.scrollToRow();
                self.migrateNextPhoto();
            });
        };
        
        this.updateProgress = function(content, width, css_class) {
            var $progress = $('.progress', self.table_row);
            var $bar = $('.bar', $progress);
            var $txt = $('.txt', $progress);
            $txt.html(content);
            $bar.css('width', width + '%');
            if (css_class) $bar.addClass(css_class);
        };
    }
    
    function Photo(set, flickr_id, url, isprimary) {
        var self = this;
        this.set = set;
        this.flickr_id = flickr_id;
        this.url = url;
        this.isprimary = isprimary;
        
        this.migrate = function() {
            var percent = Math.floor((self.set.photo_num-1) / self.set.photo_count * 100);
            self.set.updateProgress('Importing photo ' + self.set.photo_num + ' of ' + self.set.photo_count, percent);
            
            var data = {
                action : 'migrate_photo',
                photo_flickr_id : self.flickr_id,
                set_wp_id : self.set.wp_id,
                photo_url : self.url,
                isprimary : self.isprimary
            };
            $.post(ajaxurl, data, function(resp) {
                $('.thumb', self.set.table_row).html(resp);
                self.set.migrateNextPhoto();
            });
        };
    }

})(jQuery);
