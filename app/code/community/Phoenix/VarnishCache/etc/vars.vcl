C{
#include <errno.h>
#include <netinet/in.h>
#include <pthread.h>
#include <stdlib.h>
#include <string.h>

typedef void vas_f(const char *, const char *, int, const char *, int, int);
extern vas_f *VAS_Fail;

#define assert(e)							\
do {									\
	if (!(e))							\
		VAS_Fail(__func__, __FILE__, __LINE__, #e, errno, 0);	\
} while (0)

#define AZ(foo)		do { assert((foo) == 0); } while (0)
#define AN(foo)		do { assert((foo) != 0); } while (0)

#define CHECK_OBJ_NOTNULL(ptr, type_magic)				\
	do {								\
		assert((ptr) != NULL);					\
		assert((ptr)->magic == type_magic);			\
	} while (0)

#define ALLOC_OBJ(to, type_magic)					\
	do {								\
		(to) = calloc(sizeof *(to), 1);				\
		if ((to) != NULL)					\
			(to)->magic = (type_magic);			\
	} while (0)

struct sess {
	unsigned		magic;
#define SESS_MAGIC		0x2c2f9c5a
	int			fd;
	int			id;
	unsigned		xid;

	/* For the sake of inlining this, pretend struct sess ends
	   here ... */
};

struct var {
	unsigned	magic;
#define VAR_MAGIC	0xbbd57783
	unsigned	xid;
	char		*value;
};

static struct var **var_list = NULL;
static int var_list_sz = 0;
static pthread_mutex_t var_list_mtx = PTHREAD_MUTEX_INITIALIZER;

static void
var_clean(struct var *v)
{
	CHECK_OBJ_NOTNULL(v, VAR_MAGIC);
	free(v->value);
	v->value = NULL;
}

int
init_function(struct vmod_priv *priv, const struct VCL_conf *conf)
{
	AZ(pthread_mutex_lock(&var_list_mtx));
	if (var_list == NULL) {
		AZ(var_list_sz);
		var_list_sz = 256;
		var_list = malloc(sizeof(struct var *) * 256);
		AN(var_list);
int i;
		for (i = 0 ; i < var_list_sz; i++) {
			ALLOC_OBJ(var_list[i], VAR_MAGIC);
			var_list[i]->xid = 0;
			var_list[i]->value = NULL;
		}
	}
	AZ(pthread_mutex_unlock(&var_list_mtx));
	return (0);
}


static struct var *
get_var(struct sess *sp)
{
	struct var *v;

	AZ(pthread_mutex_lock(&var_list_mtx));
	while (var_list_sz <= sp->id) {
		int ns = var_list_sz*2;
		/* resize array */
		var_list = realloc(var_list, ns * sizeof(struct var_entry *));
		for (; var_list_sz < ns; var_list_sz++) {
			ALLOC_OBJ(var_list[var_list_sz], VAR_MAGIC);
			var_list[var_list_sz]->xid = 0;
			var_list[var_list_sz]->value = NULL;
		}
		assert(var_list_sz == ns);
		AN(var_list);
	}
	v = var_list[sp->id];

	if (v->xid != sp->xid) {
		var_clean(v);
		v->xid = sp->xid;
	}
	AZ(pthread_mutex_unlock(&var_list_mtx));
	return (v);
}

void
vmod_set(struct sess *sp, const char *value)
{
	struct var *v = get_var(sp);
	CHECK_OBJ_NOTNULL(v, VAR_MAGIC);
	var_clean(v);
	if (value == NULL)
		value = "";
	v->value = strdup(value);
}

const char *
vmod_get(struct sess *sp)
{
	struct var *v = get_var(sp);
	CHECK_OBJ_NOTNULL(v, VAR_MAGIC);
	return (v->value);
}
}C

sub vcl_init {
	C{
	    init_function(NULL, NULL);
	}C
}

# input: req.http.x-var-input
sub var_set {
	C{
	    vmod_set(sp, VRT_GetHdr(sp, HDR_REQ, "\014X-var-input:"));
	}C
}

# output: req.http.x-var-output
sub var_get {
	C{
	    VRT_SetHdr(sp, HDR_REQ, "\015X-var-output:", vmod_get(sp), vrt_magic_string_end);
	}C
}
